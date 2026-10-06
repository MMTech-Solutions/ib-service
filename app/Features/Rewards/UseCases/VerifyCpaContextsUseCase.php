<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCertifiedDepositsQueryData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Ports\Input\ListCertifiedDepositsPort;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\Exceptions\CpaEvidenceContractException;
use App\Features\Rewards\Factories\CpaRewardCalculationStrategyFactory;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Throwable;

final class VerifyCpaContextsUseCase
{
    public function __construct(private readonly RewardRepositoryFactory $repositoryFactory, private readonly ResolveModulesPort $modules, private readonly ListCpaEvidencePort $evidence, private readonly ListCertifiedDepositsPort $deposits, private readonly CpaRewardCalculationStrategyFactory $calculations) {}

    /** @return array{evaluated:int,qualified:int,errored:int,skipped:int} */
    public function execute(int $limit): array
    {
        $cutoff = app(DomainClock::class)->now();
        $repository = $this->repositoryFactory->make();
        $result = ['evaluated' => 0, 'qualified' => 0, 'errored' => 0, 'skipped' => 0];
        foreach ($repository->listCpaContextsWithoutReward($limit) as $context) {
            $configuration = json_decode($context->requirements_snapshot, true, 512, JSON_THROW_ON_ERROR);
            foreach ($repository->listCpaSources($context->id) as $source) {
                try {
                    if (($source->last_error_code ?? null) === 'evidence_contract_invalid') {
                        continue;
                    }
                    $from = CarbonImmutable::parse($source->observed_until ?? $context->captured_at)->utc();
                    if (! $from->lt($cutoff)) {
                        continue;
                    }
                    $rate = '0';
                    if ($source->kind === 'volume') {
                        $module = $this->modules->findByIds([$source->module_id])[0] ?? null;
                        if ($module === null || ! $module->is_active || $module->processing_status !== 'running') {
                            $repository->markCpaSource($source->id, 'suspended', null, $cutoff);

                            continue;
                        }
                        $symbols = json_decode($source->symbols_snapshot, true, 512, JSON_THROW_ON_ERROR);
                        $facts = $this->evidence->list(new ListCpaEvidenceQueryData($source->module_id, $context->referred_user_id, $from->toIso8601String(), $cutoff->toIso8601String(), $symbols));
                        foreach ($configuration['volume_modules'] as $conversion) {
                            if ($conversion['module_id'] === $source->module_id) {
                                $rate = $conversion['points_per_unit'];
                            }
                        }
                        if (bccomp($rate, '0', 8) <= 0 || $facts->deposit_facts !== []) {
                            throw new CpaEvidenceContractException('Invalid CPA volume source.');
                        }
                        $allowed = array_column($symbols, 'symbol_reference');
                        foreach ($facts->volume_facts as $fact) {
                            if (! in_array($fact->instrument_reference, $allowed, true)) {
                                throw new CpaEvidenceContractException('CPA volume outside frozen symbols.');
                            }
                        }
                    } else {
                        $facts = new CpaEvidenceData([], $this->deposits->execute(new ListCertifiedDepositsQueryData($context->referred_user_id, $from->toIso8601String(), $cutoff->toIso8601String(), $configuration['deposit_currency'], $configuration['deposit_currency_precision'])));
                    }
                    foreach ([...$facts->volume_facts, ...$facts->deposit_facts] as $fact) {
                        try {
                            $occurred = CarbonImmutable::parse($fact->occurred_at);
                        } catch (Throwable $exception) {
                            throw new CpaEvidenceContractException('CPA evidence has an invalid occurrence timestamp.', previous: $exception);
                        }
                        if ($fact->source_activity_id === '' || $fact->provider === '' || $fact->subject_external_user_id !== $context->referred_user_id || $occurred->lt($from) || ! $occurred->lt($cutoff)) {
                            throw new CpaEvidenceContractException('CPA evidence outside requested subject or interval.');
                        }
                    }
                    $calculation = $this->calculations->make('cpa_fixed_amount')->calculate(new CpaRewardCalculationInputData($configuration, $facts, $rate));
                    $repository->persistCpaSource($context, $source, $calculation->contributions, $cutoff);
                } catch (Throwable $exception) {
                    $repository->markCpaSource($source->id, 'error', $exception instanceof CpaEvidenceContractException || $exception instanceof InvalidProgressionActivityQueryException ? 'evidence_contract_invalid' : 'evidence_unavailable', $cutoff);
                }
            }
            try {
                $status = $repository->completeCpaVerification($context, $cutoff);
                $result[match ($status) {
                    'qualified' => 'qualified', 'already_qualified' => 'skipped', 'error' => 'errored', default => 'evaluated'
                }]++;
            } catch (Throwable) {
                $result['errored']++;
            }
        }

        return $result;
    }
}
