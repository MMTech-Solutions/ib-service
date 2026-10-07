<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Rewards\Actions\BuildRewardFinancialRequestAction;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\DTOs\NegativePnlCutSnapshotData;
use App\Features\Rewards\DTOs\PersistVolumeRewardData;
use App\Features\Rewards\Exceptions\CpaEvidenceContractException;
use App\Features\Rewards\Exceptions\RewardFinancialOperationNotAllowedException;
use App\Features\Rewards\Exceptions\RewardNotFoundException;
use App\Features\Rewards\Exceptions\RewardReconciliationBlockedException;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\CpaRewardCalculationStrategyFactory;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class PostgreSqlRewardRepository implements RewardRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly BuildRewardFinancialRequestAction $financialRequests) {}

    public function findNegativePnlCut(string $identityKey): ?NegativePnlCutSnapshotData
    {
        $row = $this->connection->table('negative_pnl_cut_snapshots')->where('identity_key', $identityKey)->first();

        return $row === null ? null : NegativePnlCutSnapshotData::from(json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR));
    }

    public function freezeNegativePnlCut(NegativePnlCutSnapshotData $snapshot): NegativePnlCutSnapshotData
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('negative_pnl_cut_snapshots')->insertOrIgnore([
            'id' => (string) Str::uuid7(), 'identity_key' => $snapshot->identity_key,
            'module_id' => $snapshot->module_id, 'subscription_id' => $snapshot->subscription_id,
            'account_id' => $snapshot->account_id, 'server_group_id' => $snapshot->server_group_id,
            'cadence' => $snapshot->cadence, 'occurred_from' => $snapshot->period->occurred_from,
            'occurred_until' => $snapshot->period->occurred_until,
            'snapshot' => json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->findNegativePnlCut($snapshot->identity_key);
    }

    public function persistVolumeReward(PersistVolumeRewardData $data): bool
    {
        return $this->connection->transaction(function () use ($data): bool {
            if ($this->connection->table('rewards')->where('origin_idempotency_key', $data->origin_idempotency_key)->exists()) {
                return false;
            }

            $rewardId = (string) Str::uuid7();
            $now = CarbonImmutable::now('UTC');
            try {
                $this->connection->table('rewards')->insert([
                    'id' => $rewardId,
                    'beneficiary_user_id' => $data->beneficiary_user_id,
                    'plan_id' => $data->plan_id,
                    'program_id' => $data->program_id,
                    'module_id' => $data->module_id,
                    'rule_assignment_id' => $data->rule_assignment_id,
                    'rule_id' => $data->rule_id,
                    'rule_version_id' => $data->rule_version_id,
                    'amount_minor' => $data->amount_minor,
                    'currency_code' => $data->currency_code,
                    'currency_precision' => $data->currency_precision,
                    'status' => 'pending',
                    'commission_type' => 'volume',
                    'network_level' => $data->network_level,
                    'summary_snapshot' => json_encode($data->summary_snapshot, JSON_THROW_ON_ERROR),
                    'settlement_idempotency_key' => 'ib-service:reward:'.$rewardId.':settlement',
                    'origin_idempotency_key' => $data->origin_idempotency_key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($this->connection->table('rewards')->where('origin_idempotency_key', $data->origin_idempotency_key)->exists()) {
                    return false;
                }

                throw $exception;
            }

            $this->connection->table('reward_evidence')->insert([
                'id' => (string) Str::uuid7(),
                'reward_id' => $rewardId,
                'evidence_provider' => 'broker_service',
                'evidence_type' => 'closed_trading_volume',
                'source_activity_id' => $data->source_activity_id,
                'subject_external_user_id' => $data->subject_external_user_id,
                'quantity' => $data->quantity,
                'unit_code' => $data->unit_code,
                'occurred_at' => $data->occurred_at,
                'instrument_reference' => $data->instrument_reference,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        });
    }

    public function findCpaContextId(CaptureCpaContextData $data): ?string
    {
        $id = $this->connection->table('cpa_contexts')->where('referred_user_id', $data->referred_user_id)->where('ib_user_id', $data->ib_user_id)->value('id');

        return $id === null ? null : (string) $id;
    }

    public function captureCpaContext(CaptureCpaContextData $data, object $subscription, object $rule, array $symbols, array $requirements): array
    {
        return $this->connection->transaction(function () use ($data, $subscription, $rule, $symbols, $requirements): array {
            $existing = $this->findCpaContextId($data);
            if ($existing !== null) {
                return ['id' => $existing, 'created' => false];
            }
            $contextId = (string) Str::uuid7();
            $this->connection->table('cpa_contexts')->insert([
                'id' => $contextId, 'referred_user_id' => $data->referred_user_id, 'ib_user_id' => $data->ib_user_id,
                'plan_id' => $subscription->plan_id, 'program_id' => $subscription->program_id,
                'cpa_assignment_id' => $rule->cpa_assignment_id, 'rule_id' => $rule->rule_id, 'rule_version_id' => $rule->rule_version_id,
                'symbols_snapshot' => json_encode(array_map(static fn (array $items): array => array_map(static fn (object $item): array => $item->toArray(), $items), $symbols), JSON_THROW_ON_ERROR),
                'requirements_snapshot' => json_encode($requirements, JSON_THROW_ON_ERROR), 'captured_at' => $data->captured_at,
            ]);
            $sources = [['source_key' => 'deposit', 'kind' => 'deposit', 'module_id' => null, 'symbols_snapshot' => '[]']];
            foreach ($requirements['volume_modules'] as $conversion) {
                $sources[] = ['source_key' => 'volume:'.$conversion['module_id'], 'kind' => 'volume', 'module_id' => $conversion['module_id'], 'symbols_snapshot' => json_encode(array_map(static fn (object $symbol): array => $symbol->toArray(), $symbols[$conversion['module_id']] ?? []), JSON_THROW_ON_ERROR)];
            }
            foreach ($sources as $source) {
                $this->connection->table('cpa_sources')->insert(['id' => (string) Str::uuid7(), 'cpa_context_id' => $contextId, ...$source, 'status' => 'pending']);
            }
            $this->connection->table('cpa_verification_progress')->insert([
                'id' => (string) Str::uuid7(), 'cpa_context_id' => $contextId, 'referred_user_id' => $data->referred_user_id, 'ib_user_id' => $data->ib_user_id,
                'status' => 'pending', 'observed_from' => $data->captured_at, 'created_at' => $data->captured_at, 'updated_at' => $data->captured_at,
            ]);

            return ['id' => $contextId, 'created' => true];
        });
    }

    public function listCpaContextsWithoutReward(int $limit): array
    {
        return $this->connection->table('cpa_contexts')
            ->join('cpa_verification_progress', 'cpa_verification_progress.cpa_context_id', '=', 'cpa_contexts.id')
            ->whereNull('cpa_contexts.reward_id')
            ->where('cpa_verification_progress.status', '!=', 'expired')
            ->orderBy('cpa_contexts.captured_at')
            ->limit($limit)
            ->get(['cpa_contexts.*'])
            ->all();
    }

    public function listCpaSources(string $contextId): array
    {
        return $this->connection->table('cpa_sources')->where('cpa_context_id', $contextId)->orderBy('source_key')->get()->all();
    }

    public function persistCpaSource(object $context, object $source, array $contributions, CarbonImmutable $cutoff): void
    {
        $this->connection->transaction(function () use ($context, $source, $contributions, $cutoff): void {
            $locked = $this->connection->table('cpa_contexts')->where('id', $context->id)->lockForUpdate()->first();
            if ($locked === null || $locked->reward_id !== null) {
                return;
            }
            $current = $this->connection->table('cpa_sources')->where('id', $source->id)->first();
            if ($current->last_error_code === 'evidence_contract_invalid') {
                throw new CpaEvidenceContractException('CPA source requires contract investigation.');
            }
            foreach ($contributions as $fact) {
                $existing = $this->connection->table('cpa_contributions')->where('cpa_source_id', $source->id)->where('provider', $fact->provider)->where('source_activity_id', $fact->source_activity_id)->first();
                if ($existing !== null) {
                    if (bccomp($existing->quantity, $fact->quantity, 8) !== 0 || bccomp($existing->points, $fact->points, 16) !== 0 || $existing->unit_code !== $fact->unit_code || $existing->subject_external_user_id !== $fact->subject_external_user_id || $existing->instrument_reference !== $fact->instrument_reference || CarbonImmutable::parse($existing->occurred_at)->ne(CarbonImmutable::parse($fact->occurred_at)) || $existing->currency_code !== $fact->currency_code || ($existing->amount_minor === null ? null : (int) $existing->amount_minor) !== $fact->amount_minor) {
                        throw new CpaEvidenceContractException('CPA source contradicted an immutable contribution.');
                    }

                    continue;
                }
                if ($current->observed_until !== null && CarbonImmutable::parse($fact->occurred_at)->lt(CarbonImmutable::parse($current->observed_until))) {
                    throw new CpaEvidenceContractException('CPA source published a fact before its confirmed cutoff.');
                }
                $this->connection->table('cpa_contributions')->insert([
                    'id' => (string) Str::uuid7(), 'cpa_context_id' => $context->id, 'cpa_source_id' => $source->id,
                    ...$fact->toArray(), 'verified_until' => $cutoff, 'created_at' => $cutoff,
                ]);
            }
            if ($current->observed_until === null || CarbonImmutable::parse($current->observed_until)->lte($cutoff)) {
                $this->connection->table('cpa_sources')->where('id', $source->id)->update(['observed_until' => $cutoff, 'status' => 'ready', 'last_error_code' => null, 'last_evaluated_at' => $cutoff]);
            }
        });
    }

    public function markCpaSource(string $sourceId, string $status, ?string $errorCode, CarbonImmutable $at): void
    {
        $this->connection->table('cpa_sources')->where('id', $sourceId)->where(fn ($q) => $q->whereNull('last_evaluated_at')->orWhere('last_evaluated_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('last_error_code')->orWhere('last_error_code', '!=', 'evidence_contract_invalid'))
            ->update(['status' => $status, 'last_error_code' => $errorCode, 'last_evaluated_at' => $at]);
    }

    public function expireCpaContext(object $context, string $reason, CarbonImmutable $at): bool
    {
        return $this->connection->transaction(function () use ($context, $reason, $at): bool {
            $locked = $this->connection->table('cpa_contexts')->where('id', $context->id)->lockForUpdate()->first();
            if ($locked === null || $locked->reward_id !== null) {
                return false;
            }
            $progress = $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $context->id)->lockForUpdate()->first();
            if ($progress === null || in_array($progress->status, ['expired', 'qualified'], true)) {
                return false;
            }
            $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $context->id)->update([
                'status' => 'expired',
                'expiration_reason' => $reason,
                'last_evaluated_at' => $at,
                'updated_at' => $at,
            ]);

            return true;
        });
    }

    public function completeCpaVerification(object $context, CarbonImmutable $at): string
    {
        return $this->connection->transaction(function () use ($context, $at): string {
            $locked = $this->connection->table('cpa_contexts')->where('id', $context->id)->lockForUpdate()->first();
            if ($locked->reward_id !== null) {
                return 'already_qualified';
            }
            $progress = $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $context->id)->lockForUpdate()->first();
            if ($progress !== null && $progress->status === 'expired') {
                return 'expired';
            }
            $configuration = json_decode($locked->requirements_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $totals = [];
            foreach (['volume', 'deposit'] as $kind) {
                $totals[$kind] = (string) $this->connection->table('cpa_contributions')->where('cpa_context_id', $context->id)->where('kind', $kind)->sum('points');
            }
            $calculation = app(CpaRewardCalculationStrategyFactory::class)->make('cpa_fixed_amount')->calculate(
                new CpaRewardCalculationInputData($configuration, new CpaEvidenceData([], []), initial_volume_points: $totals['volume'], initial_deposit_points: $totals['deposit'])
            );
            $error = $this->connection->table('cpa_sources')->where('cpa_context_id', $context->id)->where('status', 'error')->value('last_error_code');
            $invalidContract = $this->connection->table('cpa_sources')->where('cpa_context_id', $context->id)->where('last_error_code', 'evidence_contract_invalid')->exists();
            $qualified = $calculation->qualified && ! $invalidContract;
            $status = $qualified ? 'qualified' : ($error === null ? 'pending' : 'error');
            $depositMinor = $this->connection->table('cpa_contributions')->where('cpa_context_id', $context->id)->sum('amount_minor');
            if (bccomp((string) $depositMinor, (string) PHP_INT_MAX, 0) > 0) {
                throw new CpaEvidenceContractException('CPA deposit total exceeds integer range.');
            }
            $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $context->id)->update([
                'status' => $status, 'observed_volume_points' => $calculation->volume_points, 'observed_deposit_points' => $calculation->deposit_points,
                'observed_deposit_minor' => $depositMinor,
                'volume_satisfied' => bccomp($calculation->volume_points, $configuration['required_volume_points'], 16) >= 0,
                'deposit_satisfied' => bccomp($calculation->deposit_points, $configuration['required_deposit_points'], 16) >= 0,
                'last_evaluated_at' => $at, 'last_error_code' => $error, 'updated_at' => $at,
            ]);
            if (! $qualified) {
                return $status;
            }
            $currency = Currency::from($configuration['currency'], $configuration['currency_precision']);
            $amount = PositiveMoney::fromDecimalMajor($configuration['amount'], $currency);
            $rewardId = (string) Str::uuid7();
            $this->connection->table('rewards')->insert([
                'id' => $rewardId, 'beneficiary_user_id' => $locked->ib_user_id, 'plan_id' => $locked->plan_id, 'program_id' => $locked->program_id,
                'module_id' => null, 'rule_assignment_id' => null, 'rule_id' => $locked->rule_id, 'rule_version_id' => $locked->rule_version_id,
                'amount_minor' => $amount->minorUnits, 'currency_code' => $currency->code(), 'currency_precision' => $currency->precision(),
                'status' => 'pending', 'commission_type' => 'cpa', 'network_level' => 1,
                'summary_snapshot' => json_encode(['cpa_assignment_id' => $locked->cpa_assignment_id, 'configuration' => $configuration, 'volume_points' => $calculation->volume_points, 'deposit_points' => $calculation->deposit_points, 'sources' => $this->listCpaSources($context->id)], JSON_THROW_ON_ERROR),
                'created_at' => $at, 'updated_at' => $at,
            ]);
            foreach ($this->connection->table('cpa_contributions as c')->join('cpa_sources as s', 's.id', '=', 'c.cpa_source_id')->where('c.cpa_context_id', $context->id)->get(['c.*', 's.source_key']) as $fact) {
                $this->connection->table('reward_evidence')->insert([
                    'id' => (string) Str::uuid7(), 'reward_id' => $rewardId, 'evidence_provider' => $fact->provider, 'evidence_type' => $fact->kind === 'volume' ? 'closed_trading_volume' : 'certified_external_deposit',
                    'source_scope' => $fact->source_key, 'source_activity_id' => $fact->source_activity_id, 'subject_external_user_id' => $fact->subject_external_user_id,
                    'quantity' => $fact->quantity, 'unit_code' => $fact->unit_code, 'amount_minor' => $fact->amount_minor, 'currency_code' => $fact->currency_code,
                    'points_per_unit' => $fact->points_per_unit, 'points' => $fact->points, 'verified_until' => $fact->verified_until,
                    'occurred_at' => $fact->occurred_at, 'instrument_reference' => $fact->instrument_reference, 'created_at' => $at, 'updated_at' => $at,
                ]);
            }
            $this->connection->table('cpa_contexts')->where('id', $context->id)->update(['reward_id' => $rewardId]);

            return 'qualified';
        });
    }

    public function claimNextSettlement(CarbonImmutable $now, CarbonImmutable $retryAt, CarbonImmutable $lockExpiresAt, array $excludedIds = []): ?object
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.settlement_enabled']);

        return $this->connection->transaction(function () use ($settings, $now, $retryAt, $lockExpiresAt, $excludedIds): ?object {
            $reward = $this->connection->table('rewards')
                ->when(! $settings->get('rewards.negative_pnl.settlement_enabled'), fn ($query) => $query->where('rewards.commission_type', '!=', 'pnl'))
                ->whereNotIn('rewards.id', $excludedIds)
                ->whereNull('rewards.reconciliation_hold_at')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('reward_financial_operations')
                        ->whereColumn('reward_financial_operations.reward_id', 'rewards.id')
                        ->where('reward_financial_operations.operation_type', 'cancellation')
                        ->where('reward_financial_operations.status', '!=', 'completed');
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('reward_financial_operations')
                        ->whereColumn('reward_financial_operations.compensation_reward_id', 'rewards.id')
                        ->where('reward_financial_operations.status', '!=', 'completed');
                })
                ->where(function ($query) use ($retryAt): void {
                    $query->where('rewards.status', 'pending')->orWhere(function ($retryable) use ($retryAt): void {
                        $retryable->where('rewards.status', 'failed')->where(function ($lastAttempt) use ($retryAt): void {
                            $lastAttempt->whereNull('rewards.last_settlement_attempt_at')->orWhere('rewards.last_settlement_attempt_at', '<=', $retryAt);
                        });
                    });
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('rewards.settlement_lock_expires_at')->orWhere('rewards.settlement_lock_expires_at', '<=', $now);
                })->orderBy('rewards.created_at')->lock('FOR UPDATE SKIP LOCKED')->first(['rewards.*']);
            if ($reward === null) {
                return null;
            }
            $request = $this->financialRequests->settlement($reward);
            if ($request->network_level < 1) {
                $this->placeReconciliationHold((string) $reward->id, 'FINANCE_LEGACY_LEVEL_INVALID', $now);

                return $this->claimNextSettlement($now, $retryAt, $lockExpiresAt, [...$excludedIds, (string) $reward->id]);
            }
            $reward->settlement_request_snapshot = json_encode(get_object_vars($request), JSON_THROW_ON_ERROR);
            $token = (string) Str::uuid7();
            $key = $reward->settlement_idempotency_key ?? 'ib-service:reward:'.$reward->id.':settlement';
            $this->connection->table('rewards')->where('id', $reward->id)->update(['settlement_request_snapshot' => $reward->settlement_request_snapshot, 'settlement_idempotency_key' => $key, 'settlement_attempt_count' => (int) $reward->settlement_attempt_count + 1, 'last_settlement_attempt_at' => $now, 'settlement_lock_token' => $token, 'settlement_lock_expires_at' => $lockExpiresAt, 'updated_at' => $now]);
            $reward->settlement_idempotency_key = $key;
            $reward->settlement_lock_token = $token;

            return $reward;
        });
    }

    public function markRewardSettled(string $rewardId, string $token, string $provider, string $referenceId, CarbonImmutable $at): bool
    {
        return $this->connection->table('rewards')->where('id', $rewardId)->where('settlement_lock_token', $token)->where('settlement_lock_expires_at', '>', $at)->whereIn('status', ['pending', 'failed'])->update(['status' => 'settled', 'settlement_provider' => $provider, 'settlement_reference_id' => $referenceId, 'last_settlement_error_code' => null, 'settled_at' => $at, 'settlement_lock_token' => null, 'settlement_lock_expires_at' => null, 'updated_at' => $at]) === 1;
    }

    public function releaseSettlementClaim(string $rewardId, string $token): void
    {
        $this->connection->table('rewards')->where('id', $rewardId)->where('settlement_lock_token', $token)
            ->update(['settlement_lock_token' => null, 'settlement_lock_expires_at' => null]);
    }

    public function markRewardSettlementFailed(string $rewardId, string $token, string $errorCode, CarbonImmutable $at): bool
    {
        return $this->connection->table('rewards')->where('id', $rewardId)->where('settlement_lock_token', $token)->where('settlement_lock_expires_at', '>', $at)->whereIn('status', ['pending', 'failed'])->update(['status' => 'failed', 'last_settlement_error_code' => $errorCode, 'settlement_lock_token' => null, 'settlement_lock_expires_at' => null, 'updated_at' => $at]) === 1;
    }

    public function beginFinancialOperation(string $rewardId, string $actorId, string $type, string $reasonCode, ?string $reasonLabel, ?int $amountMinor, ?string $customKey): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.settlement_enabled', 'rewards.settlement.claim_lease_seconds']);

        return $this->connection->transaction(function () use ($settings, $rewardId, $actorId, $type, $reasonCode, $reasonLabel, $amountMinor, $customKey): array {
            $reward = $this->connection->table('rewards')->where('id', $rewardId)->lockForUpdate()->first();
            if ($reward === null) {
                throw RewardNotFoundException::forId($rewardId);
            }
            $operation = $this->connection->table('reward_financial_operations')->where('reward_id', $rewardId)->where('operation_type', $type)->first();
            if ($operation !== null && ($operation->reason_code !== $reasonCode || $operation->reason_label !== $reasonLabel || ($type === 'compensation' && ((int) $operation->amount_minor !== $amountMinor || $operation->idempotency_key !== $customKey)))) {
                throw RewardFinancialOperationNotAllowedException::create();
            }
            if ($operation !== null && $operation->status === 'completed') {
                return [$reward, $operation];
            }
            $now = CarbonImmutable::now('UTC');
            if ($reward->reconciliation_hold_at !== null) {
                throw RewardReconciliationBlockedException::create();
            }
            if ($reward->settlement_lock_expires_at !== null && CarbonImmutable::parse($reward->settlement_lock_expires_at)->greaterThan($now)) {
                throw RewardFinancialOperationNotAllowedException::create();
            }
            if ($this->connection->table('reward_financial_operations')->where('reward_id', $rewardId)->where('operation_type', '!=', $type)->where('status', '!=', 'completed')->exists()) {
                throw RewardFinancialOperationNotAllowedException::create();
            }
            $allowed = match ($type) {
                'cancellation' => in_array($reward->status, ['pending', 'failed'], true),
                'reversal' => in_array($reward->status, ['settled', 'reversal_failed', 'reversal_pending'], true),
                'compensation' => $reward->status === 'reversed', default => false,
            };
            if (! $allowed || ($type === 'compensation' && ($amountMinor === null || $amountMinor < 1)) || ($type === 'compensation' && $reward->commission_type === 'pnl' && ! $settings->get('rewards.negative_pnl.settlement_enabled'))) {
                throw RewardFinancialOperationNotAllowedException::create();
            }
            $request = $this->financialRequests->settlement($reward);
            $reward->settlement_request_snapshot = json_encode(get_object_vars($request), JSON_THROW_ON_ERROR);
            $token = (string) Str::uuid7();
            $expiresAt = $now->addSeconds(max((int) $settings->get('rewards.settlement.claim_lease_seconds'), 1));
            $this->connection->table('rewards')->where('id', $rewardId)->update(['settlement_request_snapshot' => $reward->settlement_request_snapshot, 'settlement_idempotency_key' => $request->idempotency_key, 'settlement_lock_token' => $token, 'settlement_lock_expires_at' => $expiresAt]);
            $reward->settlement_lock_token = $token;
            $reward->settlement_lock_expires_at = $expiresAt;
            $reward->settlement_idempotency_key = $request->idempotency_key;
            if ($operation === null) {
                $id = (string) Str::uuid7();
                $this->connection->table('reward_financial_operations')->insert(['id' => $id, 'reward_id' => $rewardId, 'operation_type' => $type, 'status' => 'processing', 'idempotency_key' => $customKey ?? 'ib-service:reward:'.$rewardId.':'.$type, 'requested_by_user_id' => $actorId, 'reason_code' => $reasonCode, 'reason_label' => $reasonLabel, 'amount_minor' => $amountMinor, 'currency_code' => $type === 'compensation' ? $reward->currency_code : null, 'currency_precision' => $type === 'compensation' ? $reward->currency_precision : null, 'attempt_count' => 0, 'created_at' => $now, 'updated_at' => $now]);
            } else {
                $id = (string) $operation->id;
            }
            $this->connection->table('reward_financial_operations')->where('id', $id)->update(['status' => 'processing', 'attempt_count' => ($operation->attempt_count ?? 0) + 1, 'last_attempt_at' => $now, 'last_error_code' => null, 'lock_token' => $token, 'lock_expires_at' => $expiresAt, 'updated_at' => $now]);
            if ($type === 'reversal') {
                $this->connection->table('rewards')->where('id', $rewardId)->update(['status' => 'reversal_pending']);
                $reward->status = 'reversal_pending';
            }

            return [$reward, $this->findFinancialOperation($id)];
        });
    }

    public function freezeFinancialOperationRequest(string $rewardId, string $operationId, string $token, array $request): array
    {
        return $this->financialTransaction($rewardId, $token, function () use ($rewardId, $operationId, $token, $request): array {
            $operation = $this->financialOperationForLease($rewardId, $operationId, $token);
            if ($operation->request_snapshot === null) {
                $this->connection->table('reward_financial_operations')->where('id', $operationId)->update(['request_snapshot' => json_encode($request, JSON_THROW_ON_ERROR)]);

                return $request;
            }

            return json_decode($operation->request_snapshot, true, 512, JSON_THROW_ON_ERROR);
        });
    }

    public function createCompensationReward(object $reward, object $operation, int $amountMinor, string $reasonCode, CarbonImmutable $at): string
    {
        return $this->financialTransaction((string) $reward->id, (string) $operation->lock_token, function () use ($reward, $operation, $amountMinor, $reasonCode, $at): string {
            $locked = $this->financialOperationForLease((string) $reward->id, (string) $operation->id, (string) $operation->lock_token);
            if ($locked->compensation_reward_id !== null) {
                return (string) $locked->compensation_reward_id;
            }
            $id = (string) Str::uuid7();
            $request = $this->financialRequests->settlement($reward);
            $snapshot = get_object_vars($request);
            $snapshot['reward_id'] = $id;
            $snapshot['idempotency_key'] = 'ib-service:reward:'.$id.':settlement';
            $snapshot['amount_minor'] = $amountMinor;
            $this->connection->table('rewards')->insert(['id' => $id, 'compensates_reward_id' => $reward->id, 'beneficiary_user_id' => $reward->beneficiary_user_id, 'plan_id' => $reward->plan_id, 'program_id' => $reward->program_id, 'module_id' => $reward->module_id, 'rule_assignment_id' => $reward->rule_assignment_id, 'rule_id' => $reward->rule_id, 'rule_version_id' => $reward->rule_version_id, 'amount_minor' => $amountMinor, 'currency_code' => $reward->currency_code, 'currency_precision' => $reward->currency_precision, 'status' => 'pending', 'commission_type' => $reward->commission_type, 'network_level' => $reward->network_level, 'summary_snapshot' => json_encode(['compensates_reward_id' => $reward->id, 'reason_code' => $reasonCode], JSON_THROW_ON_ERROR), 'settlement_idempotency_key' => $snapshot['idempotency_key'], 'settlement_request_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => $at, 'updated_at' => $at]);
            $this->connection->table('reward_financial_operations')->where('id', $operation->id)->update(['compensation_reward_id' => $id, 'request_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'updated_at' => $at]);

            return $id;
        });
    }

    public function completeFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, ?string $compensationRewardId, ?string $providerReferenceId, CarbonImmutable $at, string $token, ?string $outcome = null): void
    {
        $this->financialTransaction($rewardId, $token, function () use ($rewardId, $operationId, $rewardStatus, $compensationRewardId, $providerReferenceId, $at, $outcome, $token): void {
            $operation = $this->financialOperationForLease($rewardId, $operationId, $token);
            if ($operation->compensation_reward_id !== $compensationRewardId) {
                throw new RewardSettlementException('financial_lease_lost');
            }
            $updates = ['reconciliation_hold_code' => null, 'reconciliation_hold_at' => null, 'last_reconciled_at' => $at, 'settlement_lock_token' => null, 'settlement_lock_expires_at' => null, 'updated_at' => $at];
            if ($rewardStatus !== null) {
                $updates['status'] = $rewardStatus;
            }
            if ($rewardStatus === 'settled') {
                $updates += ['settlement_provider' => 'finance', 'settlement_reference_id' => $providerReferenceId, 'settled_at' => $at, 'last_settlement_error_code' => null];
            }
            $this->connection->table('rewards')->where('id', $rewardId)->update($updates);
            if ($operation->operation_type === 'compensation' && $compensationRewardId !== null) {
                $this->connection->table('rewards')->where('id', $compensationRewardId)->update(['status' => 'settled', 'settlement_provider' => 'finance', 'settlement_reference_id' => $providerReferenceId, 'settled_at' => $at, 'last_settlement_error_code' => null, 'updated_at' => $at]);
            }
            $this->connection->table('reward_financial_operations')->where('id', $operationId)->update(['status' => 'completed', 'outcome' => $outcome ?? $rewardStatus ?? 'compensated', 'compensation_reward_id' => $compensationRewardId, 'provider' => $providerReferenceId === null ? null : 'finance', 'provider_reference_id' => $providerReferenceId, 'last_error_code' => null, 'lock_token' => null, 'lock_expires_at' => null, 'completed_at' => $at, 'updated_at' => $at]);
        });
    }

    public function failFinancialOperation(string $rewardId, string $operationId, ?string $rewardStatus, string $errorCode, CarbonImmutable $at, string $token): void
    {
        $this->financialTransaction($rewardId, $token, function () use ($rewardId, $operationId, $rewardStatus, $errorCode, $at, $token): void {
            $operation = $this->financialOperationForLease($rewardId, $operationId, $token);
            $updates = ['settlement_lock_token' => null, 'settlement_lock_expires_at' => null, 'updated_at' => $at];
            if ($rewardStatus !== null) {
                $updates['status'] = $rewardStatus;
            }
            $this->connection->table('rewards')->where('id', $rewardId)->update($updates);
            if ($operation->compensation_reward_id !== null) {
                $this->connection->table('rewards')->where('id', $operation->compensation_reward_id)->update(['status' => 'failed', 'last_settlement_error_code' => $errorCode, 'updated_at' => $at]);
            }
            $this->connection->table('reward_financial_operations')->where('id', $operationId)->update(['status' => 'failed', 'last_error_code' => $errorCode, 'lock_token' => null, 'lock_expires_at' => null, 'updated_at' => $at]);
        });
    }

    public function placeReconciliationHold(string $rewardId, string $code, CarbonImmutable $at, ?string $token = null): void
    {
        if ($token !== null) {
            $this->financialTransaction($rewardId, $token, fn () => $this->placeReconciliationHold($rewardId, $code, $at));

            return;
        }
        $this->connection->table('rewards')->where('id', $rewardId)->update(['reconciliation_hold_code' => $code, 'reconciliation_hold_at' => $at, 'last_reconciled_at' => $at, 'updated_at' => $at]);
    }

    public function findFinancialOperation(string $operationId): object
    {
        return $this->connection->table('reward_financial_operations')->where('id', $operationId)->firstOrFail();
    }

    public function claimNextReconciliation(CarbonImmutable $now, CarbonImmutable $expiresAt, array $excludedIds): ?object
    {
        return $this->connection->transaction(function () use ($now, $expiresAt, $excludedIds): ?object {
            $reward = $this->connection->table('rewards')->whereNotIn('id', $excludedIds)
                ->where(fn ($query) => $query->whereNull('settlement_lock_expires_at')->orWhere('settlement_lock_expires_at', '<=', $now))
                ->where(function ($query): void {
                    $query->whereNotNull('reconciliation_hold_at')->orWhere(fn ($failed) => $failed->where('status', 'failed')->where('settlement_attempt_count', '>', 0))
                        ->orWhereExists(fn ($operations) => $operations->selectRaw('1')->from('reward_financial_operations')->whereColumn('reward_id', 'rewards.id')->where('status', '!=', 'completed'));
                })->orderByRaw('last_reconciled_at ASC NULLS FIRST')->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if ($reward === null) {
                return null;
            }
            $token = (string) Str::uuid7();
            $request = $this->financialRequests->settlement($reward);
            $reward->settlement_request_snapshot = json_encode(get_object_vars($request), JSON_THROW_ON_ERROR);
            $reward->settlement_lock_token = $token;
            $this->connection->table('rewards')->where('id', $reward->id)->update(['settlement_lock_token' => $token, 'settlement_lock_expires_at' => $expiresAt, 'settlement_request_snapshot' => $reward->settlement_request_snapshot, 'settlement_idempotency_key' => $request->idempotency_key, 'last_reconciled_at' => $now]);
            $operation = $this->connection->table('reward_financial_operations')->where('reward_id', $reward->id)->where('status', '!=', 'completed')->orderBy('created_at')->first();
            $reward->reconciliation_operation_id = $operation?->id;
            if ($operation !== null) {
                $this->connection->table('reward_financial_operations')->where('id', $operation->id)->update(['lock_token' => $token, 'lock_expires_at' => $expiresAt]);
            }

            return $reward;
        });
    }

    public function confirmReconciliation(object $reward, string $type, string $providerReferenceId, CarbonImmutable $at): void
    {
        $this->financialTransaction((string) $reward->id, (string) $reward->settlement_lock_token, function () use ($reward, $type, $providerReferenceId, $at): void {
            $updates = ['reconciliation_hold_code' => null, 'reconciliation_hold_at' => null, 'last_reconciled_at' => $at, 'settlement_lock_token' => null, 'settlement_lock_expires_at' => null, 'updated_at' => $at];
            if ($type !== 'reversal' && $reward->status !== 'reversed') {
                $updates += ['status' => 'settled', 'settlement_provider' => 'finance', 'settlement_reference_id' => $providerReferenceId, 'settled_at' => $at, 'last_settlement_error_code' => null];
            }
            $this->connection->table('rewards')->where('id', $reward->id)->update($updates);
        });
    }

    private function financialOperationForLease(string $rewardId, string $operationId, string $token): object
    {
        $operation = $this->findFinancialOperation($operationId);
        if ($operation->reward_id !== $rewardId || $operation->lock_token !== $token || $operation->status === 'completed'
            || $operation->lock_expires_at === null || ! CarbonImmutable::parse($operation->lock_expires_at)->greaterThan(CarbonImmutable::now('UTC'))) {
            throw new RewardSettlementException('financial_lease_lost');
        }

        return $operation;
    }

    private function financialTransaction(string $rewardId, string $token, \Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($rewardId, $token, $callback): mixed {
            $reward = $this->connection->table('rewards')->where('id', $rewardId)->lockForUpdate()->first();
            $expiry = $reward?->settlement_lock_expires_at;
            if ($token === '' || $reward?->settlement_lock_token !== $token || $expiry === null || ! CarbonImmutable::parse($expiry)->greaterThan(CarbonImmutable::now('UTC'))) {
                throw new RewardSettlementException('financial_lease_lost');
            }
            $result = $callback();
            if (! CarbonImmutable::parse($expiry)->greaterThan(CarbonImmutable::now('UTC'))) {
                throw new RewardSettlementException('financial_lease_lost');
            }

            return $result;
        });
    }
}
