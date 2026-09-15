<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Actions\AssertProgramLadderAction;
use App\Features\Programs\Catalog\Actions\AssertProgramModuleSelectionAction;
use App\Features\Programs\Catalog\DTOs\ProgramData;
use App\Features\Programs\Catalog\DTOs\ProgramReorderItem;
use App\Features\Programs\Catalog\DTOs\ProgramsPageData;
use App\Features\Programs\Catalog\Exceptions\ProgramConcurrencyException;
use App\Features\Programs\Catalog\Exceptions\ProgramReorderConflictException;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ReorderProgramsCommand;
use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;

final class ReorderProgramsUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly AssertProgramModuleSelectionAction $assertSelection,
        private readonly AssertProgramLadderAction $assertLadder,
    ) {}

    public function execute(ReorderProgramsCommand $command): ProgramsPageData
    {
        $this->assertSelection->assertMutable($command->planId);
        $repository = $this->repositoryFactory->make();
        $now = CarbonImmutable::now('UTC')->toISOString();

        $repository->transaction(function () use ($repository, $command, $now): void {
            $current = $repository->listByPlanId($command->planId);
            $byId = [];
            foreach ($current as $program) {
                $byId[$program->id] = $program;
            }

            $currentIds = array_keys($byId);
            $incomingIds = array_map(
                static fn (ProgramReorderItem $item): string => $item->id,
                $command->items,
            );
            $sortedCurrent = $currentIds;
            $sortedIncoming = $incomingIds;
            sort($sortedCurrent);
            sort($sortedIncoming);

            if ($sortedCurrent !== $sortedIncoming || count($incomingIds) !== count(array_unique($incomingIds))) {
                throw ProgramReorderConflictException::forPlan($command->planId);
            }

            $projected = [];
            foreach ($command->items as $index => $item) {
                $program = $byId[$item->id];
                if ($program->lockVersion !== $item->lockVersion) {
                    throw ProgramConcurrencyException::forProgram($item->id);
                }

                $program->assignPosition($index + 1, $now);
                $program->assignEntryThreshold($item->entryThreshold, $now);
                $projected[] = $program;
            }

            $this->assertLadder->assert($projected);
            $repository->reorder($command->planId, $command->items, $now);
        });

        $programs = $repository->listByPlanId($command->planId);

        return new ProgramsPageData(
            programs: array_map(
                static fn (Program $program): ProgramData => $program->toData(),
                $programs,
            ),
        );
    }
}
