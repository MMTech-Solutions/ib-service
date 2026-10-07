<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Contracts\Exceptions\VolumeRewardModuleNotOperationalException;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveVolumeRewardModulesPort;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardDistributionLimitQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardDistributionLimitPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardProgramConfigurationPort;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use App\Features\Rewards\DTOs\PersistVolumeRewardData;
use App\Features\Rewards\DTOs\VolumeRewardActivityProcessingResultData;
use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\Rewards\DTOs\VolumeRewardPreparedInputsData;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Factories\VolumeRewardCalculationStrategyFactory;
use App\Features\Rewards\Factories\VolumeRewardProcessingRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\ResolveVolumeRewardRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveVolumeRewardRuleContextPort;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveRewardBackfillStartPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessVolumeRewardsUseCase
{
    public function __construct(
        private readonly VolumeRewardProcessingRepositoryFactory $processingRepositoryFactory,
        private readonly RewardRepositoryFactory $rewardRepositoryFactory,
        private readonly ResolveVolumeRewardModulesPort $modules,
        private readonly ListVolumeRewardActivitiesPort $activities,
        private readonly ResolveVolumeRewardDistributionLimitPort $distributionLimit,
        private readonly ResolveVolumeRewardProgramConfigurationPort $programConfiguration,
        private readonly ResolveRewardUplinePort $upline,
        private readonly ResolveSubscriptionContextPort $subscriptions,
        private readonly ResolveRewardBackfillStartPort $backfillStart,
        private readonly ResolveVolumeRewardRuleContextPort $rules,
        private readonly VolumeRewardCalculationStrategyFactory $calculations,
    ) {}

    /** @return array{event_processed: int, event_retryable: int, event_rejected: int, rewards_created: int, rewards_skipped: int, periodic_pages: int} */
    public function execute(int $limit): array
    {
        $result = ['event_processed' => 0, 'event_retryable' => 0, 'event_rejected' => 0, 'rewards_created' => 0, 'rewards_skipped' => 0, 'periodic_pages' => 0];
        $repository = $this->processingRepositoryFactory->make();
        $leaseSeconds = max((int) config('rewards.volume.claim_lease_seconds', 60), 1);

        for ($processed = 0; $processed < $limit; $processed++) {
            $now = CarbonImmutable::now('UTC');
            $receipt = $repository->claimNextEvent($now, $now->addSeconds($leaseSeconds));
            if ($receipt === null) {
                break;
            }

            try {
                $activity = VolumeRewardActivityData::from(json_decode($receipt->activity, true, 512, JSON_THROW_ON_ERROR));
                $activityResult = $this->processActivity($activity, 'event');
                if ($activityResult->retry_code !== null) {
                    $this->retryReceipt($receipt, $activityResult->retry_code);
                    $result['event_retryable']++;

                    continue;
                }

                $repository->markEventProcessed((string) $receipt->id, (string) $receipt->claim_token, CarbonImmutable::now('UTC'));
                $result['event_processed']++;
                $result['rewards_created'] += $activityResult->created;
                $result['rewards_skipped'] += $activityResult->skipped;
            } catch (InvalidVolumeRewardActivityException|InvalidProgressionActivityQueryException $exception) {
                $repository->markEventRejected((string) $receipt->id, (string) $receipt->claim_token, $exception->getErrorCode(), CarbonImmutable::now('UTC'));
                $result['event_rejected']++;
            } catch (VolumeRewardModuleNotOperationalException $exception) {
                $this->retryReceipt($receipt, $exception->getErrorCode());
                $result['event_retryable']++;
            } catch (Throwable) {
                $this->retryReceipt($receipt, 'volume_reward_processing_failed');
                $result['event_retryable']++;
            }
        }

        foreach ($this->modules->execute() as $module) {
            if (! $module->is_active || $module->processing_status !== 'running') {
                continue;
            }
            try {
                $this->processPeriodicPage($module->id, $limit, $leaseSeconds, $result);
            } catch (Throwable) {
                Log::warning('Volume reward module run unavailable.', ['module_id' => $module->id]);
            }
        }

        return $result;
    }

    /** @param array{event_processed: int, event_retryable: int, event_rejected: int, rewards_created: int, rewards_skipped: int, periodic_pages: int} $result */
    private function processPeriodicPage(string $moduleId, int $limit, int $leaseSeconds, array &$result): void
    {
        $now = CarbonImmutable::now('UTC');
        $run = $this->processingRepositoryFactory->make()->claimPeriodicRun(
            $moduleId,
            $this->backfillStart->execute()->activated_at,
            $now,
            $now->addSeconds($leaseSeconds),
        );
        if ($run === null) {
            return;
        }

        $processing = $this->processingRepositoryFactory->make();
        try {
            $limitContext = $this->distributionLimit->execute(new ResolveVolumeRewardDistributionLimitQueryData(
                module_id: $moduleId,
                occurred_from: CarbonImmutable::parse((string) $run->occurred_from)->utc()->toISOString(),
                occurred_until: CarbonImmutable::parse((string) $run->occurred_until)->utc()->toISOString(),
                channel: 'periodic',
            ));
            if ($limitContext === null) {
                $processing->completePeriodicPage((string) $run->id, (string) $run->claim_token, null, CarbonImmutable::now('UTC'));
                $result['periodic_pages']++;

                return;
            }

            $page = $this->activities->execute(new ListVolumeRewardActivitiesQueryData(
                module_id: $moduleId,
                occurred_from: (string) $run->occurred_from,
                occurred_until: (string) $run->occurred_until,
                instrument_references: $limitContext->instrument_references,
                cursor: $run->cursor === null ? null : (string) $run->cursor,
                limit: $limit,
            ));
            if ($page->module_condition !== 'running' || ! $page->provider_invoked) {
                $this->retryRun($run, $page->rejection_code ?? 'module_not_operational');

                return;
            }

            foreach ($page->activities as $activity) {
                $activityResult = $this->processActivity($activity, 'periodic');
                if ($activityResult->retry_code !== null) {
                    $this->retryRun($run, $activityResult->retry_code);

                    return;
                }
                $result['rewards_created'] += $activityResult->created;
                $result['rewards_skipped'] += $activityResult->skipped;
            }

            $processing->completePeriodicPage((string) $run->id, (string) $run->claim_token, $page->next_cursor, CarbonImmutable::now('UTC'));
            $result['periodic_pages']++;
        } catch (Throwable) {
            $this->retryRun($run, 'periodic_page_failed');
        }
    }

    private function processActivity(VolumeRewardActivityData $activity, string $channel): VolumeRewardActivityProcessingResultData
    {
        $module = $this->modules->execute($activity->module_id)[0] ?? null;
        if ($module === null || ! $module->is_active || $module->processing_status !== 'running') {
            return VolumeRewardActivityProcessingResultData::retryable('module_not_operational');
        }
        if ($activity->currency_code === null || $activity->currency_precision === null) {
            return VolumeRewardActivityProcessingResultData::retryable('activity_economic_contract_incomplete');
        }
        $repository = $this->processingRepositoryFactory->make();
        $now = CarbonImmutable::now('UTC');
        $evaluation = $repository->claimEvaluation($activity, $now, $now->addSeconds(max((int) config('rewards.volume.claim_lease_seconds', 60), 1)));
        if ($evaluation === null) {
            return VolumeRewardActivityProcessingResultData::retryable('volume_evaluation_busy');
        }
        try {
            $activity = $evaluation->activity;
            $prepared = $evaluation->preparations[$channel] ?? null;
            if ($prepared === null) {
                $upline = $evaluation->distribution;
                if ($upline === null) {
                    $at = CarbonImmutable::parse($activity->occurred_at)->utc();
                    $maxLevel = -1;
                    foreach (['event', 'periodic'] as $candidate) {
                        $limit = $this->distributionLimit->execute(new ResolveVolumeRewardDistributionLimitQueryData($activity->module_id, $at->toISOString(), $at->addMicrosecond()->toISOString(), $candidate));
                        if ($limit !== null) {
                            $maxLevel = max($maxLevel, $limit->max_distribution_level);
                        }
                    }
                    if ($maxLevel < 0) {
                        $upline = ResolveRewardUplineResultData::resolved([], $now->toISOString());
                    } else {
                        $upline = $this->upline->resolve(new ResolveRewardUplineQueryData($activity->subject_external_user_id, $maxLevel));
                    }
                    if (! $upline->isResolved() || $upline->resolved_at === null) {
                        return VolumeRewardActivityProcessingResultData::retryable($upline->failure_code ?? 'upline_unavailable');
                    }
                    $repository->freezeDistribution($evaluation, $upline);
                }
                $prepared = $this->prepareActivity($activity, $channel, $upline);
                if ($prepared instanceof VolumeRewardActivityProcessingResultData) {
                    return $prepared;
                }
                $repository->freezePreparation($evaluation, $channel, $prepared);
            }
            $created = 0;
            $skipped = $prepared->skipped;
            foreach ($prepared->rewards as $reward) {
                $wasCreated = $repository->evaluationTransaction($evaluation, function () use ($repository, $evaluation, $reward): bool {
                    $created = $this->rewardRepositoryFactory->make()->persistVolumeReward($reward);
                    $repository->recordEvaluationOutcome($evaluation, $reward->origin_idempotency_key, 'reward');

                    return $created;
                });
                $wasCreated ? $created++ : $skipped++;
            }
            $repository->recordEvaluationOutcome($evaluation, $channel, 'completed');

            return VolumeRewardActivityProcessingResultData::completed($created, $skipped);
        } finally {
            $repository->releaseEvaluation($evaluation);
        }
    }

    private function prepareActivity(VolumeRewardActivityData $activity, string $channel, ResolveRewardUplineResultData $upline): VolumeRewardPreparedInputsData|VolumeRewardActivityProcessingResultData
    {
        if ($activity->currency_code === null || $activity->currency_precision === null) {
            return VolumeRewardActivityProcessingResultData::retryable('activity_economic_contract_incomplete');
        }

        $occurredAt = CarbonImmutable::parse($activity->occurred_at)->utc();
        $limit = $this->distributionLimit->execute(new ResolveVolumeRewardDistributionLimitQueryData(
            module_id: $activity->module_id,
            occurred_from: $occurredAt->toISOString(),
            occurred_until: $occurredAt->addMicrosecond()->toISOString(),
            channel: $channel,
        ));
        if ($limit === null) {
            return new VolumeRewardPreparedInputsData([], 1);
        }

        $prepared = [];
        $skipped = 0;
        $outcomes = [];
        $minimum = (string) config('rewards.minimum_amount_major', '0.01');
        foreach ($upline->beneficiaries as $beneficiary) {
            $subscription = $this->subscriptions->resolve(new ResolveSubscriptionContextQueryData(
                $beneficiary->beneficiary_external_user_id,
                $activity->occurred_at,
            ));
            if (! $subscription->found() || $subscription->context === null) {
                $outcomes[$beneficiary->beneficiary_external_user_id] = 'no_active_subscription';
                $skipped++;

                continue;
            }
            $context = $subscription->context;
            if ($context->activated_at === '' || $occurredAt->lt(CarbonImmutable::parse($context->activated_at)->utc())) {
                $outcomes[$beneficiary->beneficiary_external_user_id] = 'before_activation';
                $skipped++;

                continue;
            }

            $configuration = $this->programConfiguration->execute(new ResolveVolumeRewardProgramConfigurationQueryData(
                program_id: $context->program_id,
                module_id: $activity->module_id,
                instrument_reference: $activity->instrument_reference,
                distribution_level: $beneficiary->distribution_level,
                occurred_at: $activity->occurred_at,
                channel: $channel,
            ));
            if ($configuration === null) {
                $outcomes[$beneficiary->beneficiary_external_user_id] = 'no_configuration_for_channel';
                $skipped++;

                continue;
            }
            if ($configuration->commission_type === 'percentage' && $activity->broker_granted_commission === null) {
                return VolumeRewardActivityProcessingResultData::retryable('activity_economic_contract_incomplete');
            }

            $rule = $this->rules->execute(new ResolveVolumeRewardRuleContextQueryData(
                $context->program_id,
                $activity->module_id,
                $activity->unit_code,
                $activity->occurred_at,
            ));
            if (! $rule->found()) {
                $outcomes[$beneficiary->beneficiary_external_user_id] = 'no_applicable_rule';
                $skipped++;

                continue;
            }

            $money = $this->calculations->make('traded_volume_commission')->calculate(new VolumeRewardCalculationData(
                commission_type: $configuration->commission_type,
                quantity: $activity->quantity,
                broker_granted_commission: $activity->broker_granted_commission ?? '0',
                participation_rate: $configuration->commission_value,
                template_level_rate: $configuration->template_level_rate,
                personal_rate: $context->personal_rate,
                is_master: $context->is_master,
                master_rate: $context->master_rate,
                currency_code: $activity->currency_code,
                currency_precision: $activity->currency_precision,
                minimum_amount_major: $minimum,
            ));
            if ($money === null) {
                $outcomes[$beneficiary->beneficiary_external_user_id] = 'below_minimum_or_rounded_zero';
                $skipped++;

                continue;
            }

            $originKey = 'volume:'.hash('sha256', implode('|', [
                $activity->module_id,
                $activity->source_activity_id,
                $beneficiary->beneficiary_external_user_id,
                (string) $beneficiary->distribution_level,
                (string) $rule->rule_assignment_id,
                (string) $rule->rule_version_id,
            ]));
            $prepared[] = new PersistVolumeRewardData(
                beneficiary_user_id: $beneficiary->beneficiary_external_user_id,
                plan_id: $context->plan_id,
                program_id: $context->program_id,
                module_id: $activity->module_id,
                rule_assignment_id: (string) $rule->rule_assignment_id,
                rule_id: (string) $rule->rule_id,
                rule_version_id: (string) $rule->rule_version_id,
                amount_minor: $money->minorUnits,
                currency_code: $money->currency->code(),
                currency_precision: $money->currency->precision(),
                network_level: $beneficiary->distribution_level,
                origin_idempotency_key: $originKey,
                summary_snapshot: [
                    'minimum_amount_major' => $minimum,
                    'channel' => $channel,
                    'module_id' => $activity->module_id,
                    'source_activity_id' => $activity->source_activity_id,
                    'source_external_user_id' => $activity->subject_external_user_id,
                    'occurred_at' => $activity->occurred_at,
                    'distribution_resolved_at' => $upline->resolved_at,
                    'distribution_level' => $beneficiary->distribution_level,
                    'subscription_id' => $context->subscription_id,
                    'placement_id' => $context->placement_id,
                    'program_volume_configuration_id' => $configuration->program_volume_configuration_id,
                    'program_volume_mode' => $configuration->mode,
                    'program_symbol_configuration_id' => $configuration->program_symbol_configuration_id,
                    'payment_template_version_binding_id' => $configuration->plan_payment_template_version_binding_id,
                    'payment_template_version_id' => $configuration->payment_template_version_id,
                    'commission_type' => $configuration->commission_type,
                    'commission_value' => $configuration->commission_value,
                    'template_level_rate' => $configuration->template_level_rate,
                    'personal_rate' => $context->personal_rate,
                    'is_master' => $context->is_master,
                    'master_rate' => $context->master_rate,
                    'quantity' => $activity->quantity,
                    'unit_code' => $activity->unit_code,
                    'broker_granted_commission' => $activity->broker_granted_commission,
                    'instrument_reference' => $activity->instrument_reference,
                    'configured_currency_code' => $configuration->configured_currency_code,
                    'currency_code' => $money->currency->code(),
                    'currency_precision' => $money->currency->precision(),
                ],
                source_activity_id: $activity->source_activity_id,
                subject_external_user_id: $activity->subject_external_user_id,
                quantity: $activity->quantity,
                unit_code: $activity->unit_code,
                occurred_at: $activity->occurred_at,
                instrument_reference: $activity->instrument_reference,
            );
        }

        return new VolumeRewardPreparedInputsData($prepared, $skipped, $outcomes);
    }

    private function retryReceipt(object $receipt, string $errorCode): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->processingRepositoryFactory->make()->markEventRetryable(
            (string) $receipt->id,
            (string) $receipt->claim_token,
            $errorCode,
            $this->nextAttemptAt($now, (int) ($receipt->attempt_count ?? 1)),
        );
    }

    private function retryRun(object $run, string $errorCode): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->processingRepositoryFactory->make()->markPeriodicRunRetryable(
            (string) $run->id,
            (string) $run->claim_token,
            $errorCode,
            $now,
            $this->nextAttemptAt($now, (int) ($run->attempt_count ?? 1)),
        );
    }

    private function nextAttemptAt(CarbonImmutable $now, int $attemptCount): CarbonImmutable
    {
        $baseDelay = max((int) config('rewards.volume.retry_delay_seconds', 60), 1);
        $multiplier = 2 ** min(max($attemptCount - 1, 0), 10);

        return $now->addSeconds($baseDelay * $multiplier);
    }
}
