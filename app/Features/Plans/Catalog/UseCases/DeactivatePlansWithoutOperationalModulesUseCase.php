<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\Enums\PlanActorKind;
use App\Features\Plans\Catalog\Enums\PlanOperationalAction;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class DeactivatePlansWithoutOperationalModulesUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(
        ?string $eventId = null,
        ?string $causeModuleId = null,
        ?string $initiatingActorIamId = null,
    ): int {
        $repository = $this->repositoryFactory->make();
        $deactivated = 0;

        foreach ($repository->allActive() as $plan) {
            $repository->transaction(function () use (
                $repository,
                $plan,
                $eventId,
                $causeModuleId,
                $initiatingActorIamId,
                &$deactivated,
            ): void {
                $fresh = $repository->findById($plan->id);
                if ($fresh === null || ! $fresh->isActive) {
                    return;
                }

                $modules = array_values($this->presentPlan->index($fresh->moduleIds()));
                if ($fresh->hasOperationalModule($modules)) {
                    return;
                }

                $now = CarbonImmutable::now('UTC')->toISOString();
                if (! $fresh->deactivate($now)) {
                    return;
                }

                $repository->update($fresh, $fresh->lockVersion);
                $repository->appendOperationalChange(new PlanOperationalChange(
                    id: (string) Str::uuid7(),
                    planId: $fresh->id,
                    action: PlanOperationalAction::Deactivate,
                    actorKind: PlanActorKind::System,
                    actorIamId: null,
                    reason: 'Plan left without operational modules.',
                    previousIsActive: true,
                    nextIsActive: false,
                    causeEventId: $eventId,
                    causeModuleId: $causeModuleId,
                    initiatingActorIamId: $initiatingActorIamId,
                    occurredAt: $now,
                ));
                $deactivated++;
            });
        }

        return $deactivated;
    }
}
