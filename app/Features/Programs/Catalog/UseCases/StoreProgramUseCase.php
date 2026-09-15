<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Actions\AssertProgramLadderAction;
use App\Features\Programs\Catalog\Actions\AssertProgramModuleSelectionAction;
use App\Features\Programs\Catalog\Actions\PresentProgramAction;
use App\Features\Programs\Catalog\DTOs\ProgramDetailData;
use App\Features\Programs\Catalog\Exceptions\DuplicateProgramCodeConflictException;
use App\Features\Programs\Catalog\Exceptions\DuplicateProgramCodeException;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\StoreProgramCommand;
use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreProgramUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly AssertProgramModuleSelectionAction $assertSelection,
        private readonly AssertProgramLadderAction $assertLadder,
        private readonly PresentProgramAction $presentProgram,
    ) {}

    public function execute(StoreProgramCommand $command): ProgramDetailData
    {
        $this->assertSelection->assertMutable($command->planId, $command->moduleIds);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): ProgramDetailData {
            $existing = $repository->listByPlanId($command->planId);
            $program = Program::create(
                id: (string) Str::uuid7(),
                planId: $command->planId,
                code: $command->code,
                name: $command->name,
                description: $command->description,
                position: $repository->nextPosition($command->planId),
                entryThreshold: $command->entryThreshold,
                moduleIds: $command->moduleIds,
                generateId: static fn (): string => (string) Str::uuid7(),
                now: CarbonImmutable::now('UTC')->toISOString(),
            );
            $this->assertLadder->assert([...$existing, $program]);

            try {
                $repository->create($program);
            } catch (DuplicateProgramCodeException) {
                throw DuplicateProgramCodeConflictException::forCode($command->code);
            }

            return $this->presentProgram->toDetail($program);
        });
    }
}
