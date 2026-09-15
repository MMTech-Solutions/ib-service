<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Programs\Catalog\DTOs\ProgramData;
use App\Features\Programs\Catalog\DTOs\ProgramsPageData;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ListProgramsCommand;
use App\Features\Programs\Catalog\Models\Program;

final class ListProgramsUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
    ) {}

    public function execute(ListProgramsCommand $command): ProgramsPageData
    {
        $this->plans->resolve(new ResolvePlanContextQueryData(plan_id: $command->planId));
        $programs = $this->repositoryFactory->make()->listByPlanId($command->planId);

        return new ProgramsPageData(
            programs: array_map(
                static fn (Program $program): ProgramData => $program->toData(),
                $programs,
            ),
        );
    }
}
