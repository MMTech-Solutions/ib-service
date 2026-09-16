<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgramSubscriptionContextData;
use App\Features\Programs\Contracts\Data\V1\ResolveFirstProgramByPositionQueryData;
use App\Features\Programs\Contracts\Exceptions\ProgramNotAvailableForPlanException;
use App\Features\Programs\Contracts\Exceptions\ProgramNotFoundException;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;

final class ResolveProgramSubscriptionContextUseCase implements ResolveProgramSubscriptionContextPort
{
    public function __construct(private readonly ProgramRepositoryFactory $repositoryFactory) {}

    public function assertBelongsToPlan(AssertProgramBelongsToPlanQueryData $query): ProgramSubscriptionContextData
    {
        $program = $this->repositoryFactory->make()->findByPlanAndId($query->plan_id, $query->program_id);
        if ($program === null) {
            throw ProgramNotFoundException::forId($query->program_id);
        }

        return new ProgramSubscriptionContextData(
            id: $program->id,
            plan_id: $program->planId,
            position: $program->position,
        );
    }

    public function resolveFirstByPosition(ResolveFirstProgramByPositionQueryData $query): ProgramSubscriptionContextData
    {
        $programs = $this->repositoryFactory->make()->listByPlanId($query->plan_id);
        if ($programs === []) {
            throw ProgramNotAvailableForPlanException::forPlan($query->plan_id);
        }

        $first = $programs[0];

        return new ProgramSubscriptionContextData(
            id: $first->id,
            plan_id: $first->planId,
            position: $first->position,
        );
    }
}
