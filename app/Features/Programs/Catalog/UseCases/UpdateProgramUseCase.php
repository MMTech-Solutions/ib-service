<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Actions\AssertProgramModuleSelectionAction;
use App\Features\Programs\Catalog\Actions\PresentProgramAction;
use App\Features\Programs\Catalog\DTOs\ProgramDetailData;
use App\Features\Programs\Catalog\Exceptions\ProgramConcurrencyException;
use App\Features\Programs\Catalog\Exceptions\ProgramNotFoundException;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\UpdateProgramCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class UpdateProgramUseCase
{
    public function __construct(
        private readonly ProgramRepositoryFactory $repositoryFactory,
        private readonly AssertProgramModuleSelectionAction $assertSelection,
        private readonly PresentProgramAction $presentProgram,
    ) {}

    public function execute(UpdateProgramCommand $command): ProgramDetailData
    {
        $moduleIds = $command->moduleIds ?? [];
        $this->assertSelection->assertMutable($command->planId, $moduleIds);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): ProgramDetailData {
            $program = $repository->findByPlanAndId($command->planId, $command->programId);
            if ($program === null) {
                throw ProgramNotFoundException::forId($command->programId);
            }

            if ($program->lockVersion !== $command->lockVersion) {
                throw ProgramConcurrencyException::forProgram($command->programId);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $changed = false;
            if ($command->hasName || $command->hasDescription) {
                $changed = $program->updateAdministrativeFields(
                    $command->hasName ? (string) $command->name : $program->name,
                    $command->hasDescription ? $command->description : $program->description,
                    $now,
                ) || $changed;
            }

            if ($command->moduleIds !== null) {
                $changed = $program->replaceSelections(
                    $command->moduleIds,
                    static fn (): string => (string) Str::uuid7(),
                    $now,
                ) || $changed;
            }

            if ($changed) {
                $repository->update($program, $command->lockVersion);
            }

            return $this->presentProgram->toDetail($program);
        });
    }
}
