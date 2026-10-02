<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramCpaSymbolsQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramCpaSymbolsPort;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CaptureCpaContextResultData;
use App\Features\Rewards\Exceptions\CpaCaptureNotApplicableException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\ResolveCpaRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveCpaRuleContextPort;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Database\UniqueConstraintViolationException;

final class CaptureCpaContextUseCase implements CaptureCpaContextPort
{
    public function __construct(
        private readonly RewardRepositoryFactory $repositoryFactory,
        private readonly ResolveSubscriptionContextPort $subscriptions,
        private readonly ResolveProgramContextPort $programs,
        private readonly ResolveProgramCpaSymbolsPort $programCpaSymbols,
        private readonly ResolveCpaRuleContextPort $rules,
        private readonly ResolveModulesPort $modules,
    ) {}

    public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData
    {
        $repository = $this->repositoryFactory->make();
        $existing = $repository->findCpaContextId($data);
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
        $rule = $this->rules->resolve(new ResolveCpaRuleContextQueryData($context->program_id, $data->captured_at));
        if (! $rule->found()) {
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

        $symbols = $this->programCpaSymbols->resolve(new ResolveProgramCpaSymbolsQueryData($context->program_id, (string) $rule->module_id, $data->captured_at));
        if ($symbols === []) {
            throw CpaCaptureNotApplicableException::create('cpa_symbols_absent');
        }

        $configuration = $rule->configuration;
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
            $captured = $repository->captureCpaContext($data, $context, $rule, $symbols, $requirements);

            return new CaptureCpaContextResultData($captured['id'], $captured['created']);
        } catch (UniqueConstraintViolationException) {
            $existing = $repository->findCpaContextId($data);
            if ($existing !== null) {
                return new CaptureCpaContextResultData($existing, false);
            }

            throw CpaCaptureNotApplicableException::create('cpa_context_concurrency_conflict');
        }
    }
}
