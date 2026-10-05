<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\CaptureProgressionLadderQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use App\Features\Programs\Contracts\Exceptions\ProgramNotAvailableForPlanException;
use App\Features\Programs\Contracts\Ports\Input\CaptureProgressionLadderPort;

final class CaptureProgressionLadderUseCase implements CaptureProgressionLadderPort
{
    public function __construct(private readonly ProgramRepositoryFactory $repositoryFactory) {}

    public function execute(CaptureProgressionLadderQueryData $query): ProgressionLadderData
    {
        $planId = $query->plan_id;
        $programs = $this->repositoryFactory->make()->listByPlanId($planId);
        if ($programs === []) {
            throw ProgramNotAvailableForPlanException::forPlan($planId);
        }

        return new ProgressionLadderData(array_map(static fn ($program): array => [
            'program_id' => $program->id, 'position' => $program->position,
            'entry_threshold' => (string) $program->entryThreshold,
        ], $programs));
    }
}
