<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

final class VerifyCpaContextsUseCase
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ResolveModulesPort $modules,
        private readonly ListCpaEvidencePort $evidence,
    ) {}

    public function execute(int $limit): array
    {
        $cutoff = CarbonImmutable::now('UTC');
        $contexts = $this->connection->table('cpa_contexts as contexts')
            ->join('cpa_verification_progress as progress', 'progress.cpa_context_id', '=', 'contexts.id')
            ->whereNull('contexts.reward_id')
            ->orderBy('contexts.captured_at')
            ->limit($limit)
            ->get(['contexts.*', 'progress.observed_volume', 'progress.observed_deposit_minor', 'progress.observed_until'])
            ->all();
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
                $volume = $this->sumVolume($incrementalEvidence ? (string) $context->observed_volume : '0', $delta->volume_facts);
                $deposit = $this->sumDeposits($incrementalEvidence ? (int) $context->observed_deposit_minor : 0, $delta->deposit_facts);
                $qualified = bccomp($volume, (string) $requirements['required_volume'], 8) >= 0 && $deposit >= (int) $requirements['required_deposit_minor'];

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
                    $volume = $this->sumVolume('0', $full->volume_facts);
                    $deposit = $this->sumDeposits(0, $full->deposit_facts);
                    $qualified = bccomp($volume, (string) $requirements['required_volume'], 8) >= 0 && $deposit >= (int) $requirements['required_deposit_minor'];
                    $this->persist($context, $requirements, $full, $volume, $deposit, $cutoff, $qualified);
                    $result[$qualified ? 'qualified' : 'evaluated']++;
                } elseif ($qualified) {
                    $this->persist($context, $requirements, $delta, $volume, $deposit, $cutoff, true);
                    $result['qualified']++;
                } else {
                    $this->updateProgress((string) $context->id, $volume, $deposit, $cutoff, 'pending', null, $requirements);
                    $result['evaluated']++;
                }
            } catch (Throwable) {
                $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $context->id)->update([
                    'status' => 'error', 'last_error_code' => 'evidence_unavailable', 'last_evaluated_at' => $cutoff, 'updated_at' => $cutoff,
                ]);
                $result['errored']++;
            }
        }

        return $result;
    }

    private function persist(object $context, array $requirements, object $evidence, string $volume, int $deposit, CarbonImmutable $cutoff, bool $qualified): void
    {
        $this->connection->transaction(function () use ($context, $requirements, $evidence, $volume, $deposit, $cutoff, $qualified): void {
            $locked = $this->connection->table('cpa_contexts')->where('id', $context->id)->lockForUpdate()->first();
            if ($locked === null || $locked->reward_id !== null) {
                return;
            }
            $this->updateProgress((string) $context->id, $volume, $deposit, $cutoff, $qualified ? 'qualified' : 'pending', null, $requirements);
            if (! $qualified) {
                return;
            }
            $currency = Currency::from((string) $requirements['currency_code'], (int) $requirements['currency_precision']);
            $amount = PositiveMoney::fromDecimalMajor((string) $requirements['amount'], $currency);
            $rewardId = (string) Str::uuid7();
            $now = $cutoff->toIso8601String();
            $this->connection->table('rewards')->insert([
                'id' => $rewardId, 'beneficiary_user_id' => $context->ib_user_id,
                'plan_id' => $context->plan_id, 'program_id' => $context->program_id, 'module_id' => $context->module_id,
                'rule_assignment_id' => $context->rule_assignment_id, 'rule_id' => $context->rule_id, 'rule_version_id' => $context->rule_version_id,
                'amount_minor' => $amount->minorUnits, 'currency_code' => $currency->code(), 'currency_precision' => $currency->precision(),
                'status' => 'pending', 'summary_snapshot' => json_encode(['observed_volume' => $volume, 'observed_deposit_minor' => $deposit, 'observed_until' => $now], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($evidence->volume_facts as $fact) {
                $this->connection->table('reward_evidence')->insert([
                    'id' => (string) Str::uuid7(), 'reward_id' => $rewardId, 'evidence_provider' => 'broker_service', 'evidence_type' => 'closed_trading_volume',
                    'source_activity_id' => $fact->source_activity_id, 'subject_external_user_id' => $fact->subject_external_user_id, 'quantity' => $fact->quantity,
                    'unit_code' => $fact->unit_code, 'occurred_at' => $fact->occurred_at, 'instrument_reference' => $fact->instrument_reference, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($evidence->deposit_facts as $fact) {
                $this->connection->table('reward_evidence')->insert([
                    'id' => (string) Str::uuid7(), 'reward_id' => $rewardId, 'evidence_provider' => 'finance', 'evidence_type' => 'certified_external_deposit',
                    'source_activity_id' => $fact['source_activity_id'], 'subject_external_user_id' => $fact['subject_external_user_id'], 'amount_minor' => $fact['amount_minor'],
                    'currency_code' => $fact['currency_code'], 'occurred_at' => $fact['occurred_at'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $this->connection->table('cpa_contexts')->where('id', $context->id)->update(['reward_id' => $rewardId]);
        });
    }

    /** @param array<string, mixed>|null $requirements */
    private function updateProgress(string $contextId, string $volume, int $deposit, CarbonImmutable $cutoff, string $status, ?string $error, ?array $requirements = null): void
    {
        $this->connection->table('cpa_verification_progress')->where('cpa_context_id', $contextId)->update([
            'status' => $status, 'observed_volume' => $volume, 'observed_deposit_minor' => $deposit,
            'volume_satisfied' => $requirements !== null && bccomp($volume, (string) $requirements['required_volume'], 8) >= 0,
            'deposit_satisfied' => $requirements !== null && $deposit >= (int) $requirements['required_deposit_minor'],
            'observed_until' => $cutoff, 'last_evaluated_at' => $cutoff, 'last_error_code' => $error, 'updated_at' => $cutoff,
        ]);
    }

    private function sumVolume(string $initial, array $facts): string
    {
        foreach ($facts as $fact) {
            $initial = bcadd($initial, $fact->quantity, 8);
        }

        return $initial;
    }

    private function sumDeposits(int $initial, array $facts): int
    {
        foreach ($facts as $fact) {
            $initial += $fact['amount_minor'];
        }

        return $initial;
    }
}
