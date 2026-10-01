<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CaptureCpaContextResultData;
use App\Features\Rewards\Exceptions\CpaCaptureNotApplicableException;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class CaptureCpaContextUseCase implements CaptureCpaContextPort
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ResolveSubscriptionContextPort $subscriptions,
        private readonly ResolveProgramContextPort $programs,
        private readonly ResolveModulesPort $modules,
    ) {}

    public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData
    {
        $existing = $this->existing($data);
        if ($existing !== null) {
            return new CaptureCpaContextResultData($existing, false);
        }

        $subscription = $this->subscriptions->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $data->referred_user_id,
            occurred_at: $data->captured_at,
        ));
        if (! $subscription->found()) {
            throw CpaCaptureNotApplicableException::create('subscription_context_absent');
        }

        $context = $subscription->context;
        $rule = $this->ruleContext($context->program_id, $data->captured_at);
        if ($rule === null) {
            throw CpaCaptureNotApplicableException::create('cpa_rule_assignment_absent');
        }

        $this->programs->assertSelectedModule(new AssertSelectedModuleQueryData(
            plan_id: $context->plan_id,
            program_id: $context->program_id,
            module_id: (string) $rule->module_id,
        ));
        $module = $this->modules->findByIds([(string) $rule->module_id])[0];
        if (! $module->is_active || $module->processing_status !== 'running') {
            throw CpaCaptureNotApplicableException::create('module_not_operational');
        }

        $symbols = $this->symbols($context->program_id, (string) $rule->module_id, $data->captured_at);
        if ($symbols === []) {
            throw CpaCaptureNotApplicableException::create('cpa_symbols_absent');
        }

        $configuration = is_string($rule->configuration) ? json_decode($rule->configuration, true) : $rule->configuration;
        if (! is_array($configuration)) {
            throw CpaCaptureNotApplicableException::create('cpa_rule_configuration_invalid');
        }
        $currencyPrecision = $configuration['currency_precision'] ?? null;
        if (! is_int($currencyPrecision)) {
            throw CpaCaptureNotApplicableException::create('cpa_currency_precision_absent');
        }
        $currency = Currency::from((string) ($configuration['currency'] ?? ''), $currencyPrecision);
        $deposit = PositiveMoney::fromDecimalMajor((string) ($configuration['required_deposit'] ?? ''), $currency);
        $requirements = [
            'strategy_type' => 'cpa_fixed_amount',
            'amount' => (string) ($configuration['amount'] ?? ''),
            'currency_code' => $currency->code(),
            'currency_precision' => $currency->precision(),
            'required_deposit_minor' => $deposit->minorUnits,
            'required_volume' => (string) ($configuration['required_volume'] ?? ''),
            'volume_unit_code' => (string) ($configuration['volume_unit_code'] ?? ''),
        ];

        try {
            return $this->connection->transaction(function () use ($data, $context, $rule, $symbols, $requirements): CaptureCpaContextResultData {
                $existing = $this->existing($data);
                if ($existing !== null) {
                    return new CaptureCpaContextResultData($existing, false);
                }

                $contextId = (string) Str::uuid7();
                $this->connection->table('cpa_contexts')->insert([
                    'id' => $contextId,
                    'referred_user_id' => $data->referred_user_id,
                    'ib_user_id' => $data->ib_user_id,
                    'plan_id' => $context->plan_id,
                    'program_id' => $context->program_id,
                    'module_id' => (string) $rule->module_id,
                    'rule_id' => (string) $rule->rule_id,
                    'rule_version_id' => (string) $rule->rule_version_id,
                    'symbols_snapshot' => json_encode($symbols, JSON_THROW_ON_ERROR),
                    'requirements_snapshot' => json_encode($requirements, JSON_THROW_ON_ERROR),
                    'captured_at' => $data->captured_at,
                ]);
                $this->connection->table('cpa_verification_progress')->insert([
                    'id' => (string) Str::uuid7(), 'cpa_context_id' => $contextId,
                    'referred_user_id' => $data->referred_user_id, 'ib_user_id' => $data->ib_user_id,
                    'status' => 'pending', 'observed_volume' => '0', 'required_volume' => $requirements['required_volume'],
                    'volume_unit_code' => $requirements['volume_unit_code'], 'observed_deposit_minor' => 0,
                    'required_deposit_minor' => $requirements['required_deposit_minor'], 'currency_code' => $requirements['currency_code'],
                    'volume_satisfied' => false, 'deposit_satisfied' => false, 'observed_from' => $data->captured_at,
                    'created_at' => $data->captured_at, 'updated_at' => $data->captured_at,
                ]);

                return new CaptureCpaContextResultData($contextId, true);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->existing($data);
            if ($existing !== null) {
                return new CaptureCpaContextResultData($existing, false);
            }

            throw CpaCaptureNotApplicableException::create('cpa_context_concurrency_conflict');
        }
    }

    private function existing(CaptureCpaContextData $data): ?string
    {
        $id = $this->connection->table('cpa_contexts')->where('referred_user_id', $data->referred_user_id)->where('ib_user_id', $data->ib_user_id)->value('id');

        return $id === null ? null : (string) $id;
    }

    private function ruleContext(string $programId, string $capturedAt): ?object
    {
        return $this->connection->table('program_cpa_rule_assignments as cpa')
            ->join('rule_assignments as assignments', function ($join): void {
                $join->on('assignments.program_id', '=', 'cpa.program_id')->on('assignments.rule_id', '=', 'cpa.rule_id')->on('assignments.rule_version_id', '=', 'cpa.rule_version_id');
            })->join('rules', 'rules.id', '=', 'cpa.rule_id')->join('rule_versions', 'rule_versions.id', '=', 'cpa.rule_version_id')
            ->where('cpa.program_id', $programId)->where('cpa.starts_at', '<=', $capturedAt)
            ->where(fn ($query) => $query->whereNull('cpa.ends_at')->orWhere('cpa.ends_at', '>', $capturedAt))
            ->where('assignments.starts_at', '<=', $capturedAt)
            ->where(fn ($query) => $query->whereNull('assignments.ends_at')->orWhere('assignments.ends_at', '>', $capturedAt))
            ->where('rules.strategy_type', 'cpa_fixed_amount')->where('rule_versions.status', 'published')
            ->select(['cpa.rule_id', 'cpa.rule_version_id', 'assignments.module_id', 'rule_versions.configuration'])->first();
    }

    /** @return list<array{symbol_reference: string, server_group_reference: string, currency_code: string}> */
    private function symbols(string $programId, string $moduleId, string $capturedAt): array
    {
        return $this->connection->table('program_symbol_configurations')
            ->where('program_id', $programId)->where('module_id', $moduleId)->where('use_for_cpa', true)
            ->where('starts_at', '<=', $capturedAt)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $capturedAt))
            ->orderBy('symbol_reference')->get(['symbol_reference', 'server_group_reference', 'currency_code'])
            ->map(fn (object $symbol): array => ['symbol_reference' => (string) $symbol->symbol_reference, 'server_group_reference' => (string) $symbol->server_group_reference, 'currency_code' => (string) $symbol->currency_code])->all();
    }
}
