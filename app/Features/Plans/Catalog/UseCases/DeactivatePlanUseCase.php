<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\Enums\PlanActorKind;
use App\Features\Plans\Catalog\Enums\PlanOperationalAction;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\DeactivatePlanCommand;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class DeactivatePlanUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(DeactivatePlanCommand $command): PlanDetailData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): PlanDetailData {
            $plan = $repository->findById($command->planId);
            if ($plan === null) {
                throw PlanNotFoundException::forId($command->planId);
            }

            if ($plan->lockVersion !== $command->lockVersion) {
                throw PlanConcurrencyException::forPlan($command->planId);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $previousIsActive = $plan->isActive;
            if (! $plan->deactivate($now)) {
                return $this->presentPlan->toDetail($plan);
            }

            $repository->update($plan, $command->lockVersion);
            $repository->appendOperationalChange(new PlanOperationalChange(
                id: (string) Str::uuid7(),
                planId: $plan->id,
                action: PlanOperationalAction::Deactivate,
                actorKind: PlanActorKind::Iam,
                actorIamId: $command->actorIamId,
                reason: $command->reason,
                previousIsActive: $previousIsActive,
                nextIsActive: false,
                causeEventId: null,
                causeModuleId: null,
                initiatingActorIamId: null,
                occurredAt: $now,
            ));

            return $this->presentPlan->toDetail($plan);
        });
    }
}
