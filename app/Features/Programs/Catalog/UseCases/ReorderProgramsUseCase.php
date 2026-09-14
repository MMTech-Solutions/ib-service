<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Actions\AssertProgramModuleSelectionAction;
use App\Features\Programs\Catalog\DTOs\ProgramData;
use App\Features\Programs\Catalog\DTOs\ProgramsPageData;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ReorderProgramsCommand;
use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;

final class ReorderProgramsUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly AssertProgramModuleSelectionAction $assertSelection,
    ) {}

    public function execute(ReorderProgramsCommand $command): ProgramsPageData
    {
        $this->assertSelection->assertMutable($command->planId);
        $repository = $this->repositoryFactory->make();
        $repository->reorder(
            $command->planId,
            $command->programIds,
            CarbonImmutable::now('UTC')->toISOString(),
        );

        $programs = $repository->listByPlanId($command->planId);

        return new ProgramsPageData(
            programs: array_map(
                static fn (Program $program): ProgramData => $program->toData(),
                $programs,
            ),
        );
    }
}
