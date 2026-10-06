<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveCpaEvidenceCapabilityPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramContextQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramCpaSymbolsQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramCpaSymbolsPort;
use App\Features\Rules\Assignments\DTOs\CpaConfigurationData;
use App\Features\Rules\Assignments\Exceptions\RuleVersionNotAssignableException;
use App\Features\Rules\Assignments\Factories\CpaConfigurationRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\CpaConfigurationCommand;
use App\Features\Rules\Services\Strategies\CpaFixedAmountStrategy;
use App\SharedFeatures\Clock\DomainClock;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Validation\ValidationException;

final class ManageCpaConfigurationUseCase
{
    public function __construct(private readonly CpaConfigurationRepositoryFactory $repositoryFactory, private readonly ResolveProgramContextPort $programs, private readonly ResolvePlanContextPort $plans, private readonly ResolveProgramCpaSymbolsPort $symbols, private readonly ResolveModulesPort $modules, private readonly ResolveCpaEvidenceCapabilityPort $evidence, private readonly UserContext $user) {}

    public function execute(CpaConfigurationCommand $command): ?CpaConfigurationData
    {
        $program = $this->programs->resolve(new ResolveProgramContextQueryData(null, $command->program_id));
        $repository = $this->repositoryFactory->make();
        if ($command->operation === 'GET') {
            return $repository->current($program->id);
        }
        $this->plans->assertEnabledModuleIds(new AssertEnabledModuleIdsQueryData($program->plan_id));
        $at = app(DomainClock::class)->now()->toISOString();
        if ($command->operation === 'DELETE') {
            $repository->withdraw($program->id, $this->user->id(), $at);

            return null;
        }
        $version = $repository->publishedVersion($program->plan_id, (string) $command->rule_version_id);
        if ($version === null) {
            throw RuleVersionNotAssignableException::forId((string) $command->rule_version_id);
        }
        (new CpaFixedAmountStrategy)->validate(1, $version['configuration']);
        $ids = array_column($version['configuration']['volume_modules'], 'module_id');
        $this->plans->assertEnabledModuleIds(new AssertEnabledModuleIdsQueryData($program->plan_id, $ids));
        foreach ($this->modules->findByIds($ids) as $module) {
            if (! in_array($module->id, $program->selected_module_ids, true) || ! $this->evidence->execute($module->code) || $this->symbols->resolve(new ResolveProgramCpaSymbolsQueryData($program->id, $module->id, $at)) === []) {
                throw ValidationException::withMessages(['rule_version_id' => 'Every CPA module requires a selected module, implemented volume provider and CPA symbols.']);
            }
        }

        return $repository->replace($program->id, $version['rule_id'], (string) $command->rule_version_id, $this->user->id(), $at);
    }
}
