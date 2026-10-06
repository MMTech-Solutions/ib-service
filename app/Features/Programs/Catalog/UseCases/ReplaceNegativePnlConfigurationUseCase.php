<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Data\V1\ResolvePaymentTemplateBindingQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePaymentTemplateBindingPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Programs\Catalog\Exceptions\InvalidNegativePnlConfigurationException;
use App\Features\Programs\Catalog\Factories\NegativePnlConfigurationRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ReplaceNegativePnlConfigurationCommand;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlModuleConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateRatesPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Rules\Contracts\Data\V1\ResolveNegativePnlRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveNegativePnlRuleContextPort;
use App\SharedFeatures\Clock\DomainClock;
use App\SharedFeatures\User\Context\UserContext;

final class ReplaceNegativePnlConfigurationUseCase
{
    public function __construct(private readonly NegativePnlConfigurationRepositoryFactory $repositories, private readonly ResolveProgramSubscriptionContextPort $programs, private readonly ResolvePlanContextPort $plans, private readonly ResolveModulesPort $modules, private readonly ResolveNegativePnlRuleContextPort $rules, private readonly ResolvePaymentTemplateBindingPort $bindings, private readonly ResolvePaymentTemplateRatesPort $templates, private readonly UserContext $user) {}

    public function execute(ReplaceNegativePnlConfigurationCommand $command): ?NegativePnlProgramConfigurationData
    {
        $this->programs->assertBelongsToPlan(new AssertProgramBelongsToPlanQueryData($command->plan_id, $command->program_id));
        $repository = $this->repositories->make();

        return $repository->transactionForProgram($command->program_id, function () use ($command, $repository): ?NegativePnlProgramConfigurationData {
            $at = app(DomainClock::class)->now()->toISOString();
            $moduleIds = array_values(array_unique(array_column($command->modules, 'module_id')));
            $this->plans->assertEnabledModuleIds(new AssertEnabledModuleIdsQueryData($command->plan_id, $moduleIds));
            $modules = $this->modules->findByIds($moduleIds);
            if (count($modules) !== count($moduleIds) || array_filter($modules, static fn ($module): bool => $module->code !== 'broker') !== []) {
                throw InvalidNegativePnlConfigurationException::forReason('Only Broker modules enabled in the plan are allowed.');
            }
            $seen = [];
            $modules = [];
            foreach ($command->modules as $group) {
                $key = json_encode([$group['module_id']], JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    throw InvalidNegativePnlConfigurationException::forReason('Duplicate module.');
                }
                $seen[$key] = true;
                $rule = $this->rules->execute(new ResolveNegativePnlRuleContextQueryData($command->plan_id, $command->program_id, $group['module_id'], $group['rule_version_id'], $at));
                $versionId = $this->bindings->execute(new ResolvePaymentTemplateBindingQueryData($command->plan_id, $rule->plan_payment_template_version_binding_id));
                $levels = $versionId === null ? null : $this->templates->execute($versionId);
                if ($levels === null || $levels === []) {
                    throw InvalidNegativePnlConfigurationException::forReason('The rule binding must belong to this plan and reference a published payment template version with levels.');
                }
                $modules[] = new NegativePnlModuleConfigurationData($group['module_id'], $rule->assignment_id, $rule->rule_id, $rule->rule_version_id, $rule->plan_payment_template_version_binding_id, $versionId, $levels);
            }

            return $repository->replace($command->program_id, $command->cadence, $this->user->id(), $at, $modules);
        });
    }
}
