<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\Factories\CpaRewardCalculationStrategyFactory;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use Carbon\CarbonImmutable;
use Throwable;

final class VerifyCpaContextsUseCase
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositoryFactory,
        private readonly ResolveModulesPort $modules,
        private readonly ListCpaEvidencePort $evidence,
        private readonly CpaRewardCalculationStrategyFactory $calculations,
    ) {}

    public function execute(int $limit): array
    {
        $cutoff = CarbonImmutable::now('UTC');
        $repository = $this->repositoryFactory->make();
        $contexts = $repository->listCpaContextsWithoutReward($limit);
        $result = ['evaluated' => 0, 'qualified' => 0, 'errored' => 0, 'skipped' => 0];
        $incrementalEvidence = (bool) config('rewards.cpa.incremental_evidence', false);

        foreach ($contexts as $context) {
            try {
                $module = $this->modules->findByIds([(string) $context->module_id])[0];
                if (! $module->is_active || $module->processing_status !== 'running') {
                    $result['skipped']++;

                    continue;
                }
                $capturedAt = CarbonImmutable::parse($context->captured_at)->utc();
                $from = $incrementalEvidence && $context->observed_until !== null
                    ? CarbonImmutable::parse($context->observed_until)->utc()
                    : $capturedAt;
                $requirements = json_decode((string) $context->requirements_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $symbols = json_decode((string) $context->symbols_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $delta = $this->evidence->list(new ListCpaEvidenceQueryData(
                    (string) $context->module_id,
                    (string) $context->referred_user_id,
                    $from->toIso8601String(),
                    $cutoff->toIso8601String(),
                    (string) $requirements['currency_code'],
                    (int) $requirements['currency_precision'],
                    $symbols,
                ));
                $strategy = $this->calculations->make('cpa_fixed_amount');
                $calculation = $strategy->calculate(new CpaRewardCalculationInputData(
                    required_volume: (string) $requirements['required_volume'],
                    required_deposit_minor: (int) $requirements['required_deposit_minor'],
                    evidence: $delta,
                    initial_volume: $incrementalEvidence ? (string) $context->observed_volume : '0',
                    initial_deposit_minor: $incrementalEvidence ? (int) $context->observed_deposit_minor : 0,
                ));
                $volume = $calculation->volume;
                $deposit = $calculation->deposit_minor;
                $qualified = $calculation->qualified;

                if ($qualified && $incrementalEvidence) {
                    $full = $this->evidence->list(new ListCpaEvidenceQueryData(
                        (string) $context->module_id,
                        (string) $context->referred_user_id,
                        $capturedAt->toIso8601String(),
                        $cutoff->toIso8601String(),
                        (string) $requirements['currency_code'],
                        (int) $requirements['currency_precision'],
                        $symbols,
                    ));
                    $calculation = $strategy->calculate(new CpaRewardCalculationInputData(
                        required_volume: (string) $requirements['required_volume'],
                        required_deposit_minor: (int) $requirements['required_deposit_minor'],
                        evidence: $full,
                    ));
                    $volume = $calculation->volume;
                    $deposit = $calculation->deposit_minor;
                    $qualified = $calculation->qualified;
                    $repository->persistQualifiedCpaContext($context, $requirements, $full, $volume, $deposit, $cutoff, $qualified);
                    $result[$qualified ? 'qualified' : 'evaluated']++;
                } elseif ($qualified) {
                    $repository->persistQualifiedCpaContext($context, $requirements, $delta, $volume, $deposit, $cutoff, true);
                    $result['qualified']++;
                } else {
                    $repository->updateCpaProgress((string) $context->id, $volume, $deposit, $cutoff, 'pending', null, $requirements);
                    $result['evaluated']++;
                }
            } catch (Throwable) {
                $repository->updateCpaProgress((string) $context->id, (string) $context->observed_volume, (int) $context->observed_deposit_minor, $cutoff, 'error', 'evidence_unavailable');
                $result['errored']++;
            }
        }

        return $result;
    }
}
