<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgramContextData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramContextQueryData;
use App\Features\Programs\Contracts\Exceptions\ModuleNotSelectedOnProgramException;
use App\Features\Programs\Contracts\Exceptions\ProgramNotFoundException;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;

final class ResolveProgramContextUseCase implements ResolveProgramContextPort
{
    public function __construct(private readonly ProgramRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolveProgramContextQueryData $query): ProgramContextData
    {
        $program = $this->repositoryFactory->make()->findByPlanAndId($query->plan_id, $query->program_id);
        if ($program === null) {
            throw ProgramNotFoundException::forId($query->program_id);
        }

        return new ProgramContextData(
            id: $program->id,
            plan_id: $program->planId,
            selected_module_ids: $program->moduleIds(),
        );
    }

    public function assertSelectedModule(AssertSelectedModuleQueryData $query): ProgramContextData
    {
        $context = $this->resolve(new ResolveProgramContextQueryData(
            plan_id: $query->plan_id,
            program_id: $query->program_id,
        ));
        if (! in_array($query->module_id, $context->selected_module_ids, true)) {
            throw ModuleNotSelectedOnProgramException::forIds($query->program_id, $query->module_id);
        }

        return $context;
    }
}
