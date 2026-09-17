<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Enums\PlanActorKind;
use App\Features\Plans\Catalog\Enums\PlanOperationalAction;
use App\Features\Plans\Catalog\Exceptions\PlanCannotArchiveWithOpenSubscriptionsException;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ArchivePlanCommand;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use App\Features\Subscriptions\Contracts\Data\V1\HasOpenSubscriptionsForPlanQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ArchivePlanUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly HasOpenSubscriptionsForPlanPort $openSubscriptions,
    ) {}

    public function execute(ArchivePlanCommand $command): void
    {
        $repository = $this->repositoryFactory->make();

        $repository->transaction(function () use ($repository, $command): void {
            $repository->lockAscending([$command->planId]);

            $plan = $repository->findByIdIncludingArchived($command->planId);
            if ($plan === null) {
                throw PlanNotFoundException::forId($command->planId);
            }

            if ($plan->lockVersion !== $command->lockVersion) {
                throw PlanConcurrencyException::forPlan($command->planId);
            }

            if ($this->openSubscriptions->hasOpen(new HasOpenSubscriptionsForPlanQueryData(
                plan_id: $plan->id,
            ))) {
                throw PlanCannotArchiveWithOpenSubscriptionsException::forPlan($plan->id);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $previousIsActive = $plan->isActive;
            if (! $plan->archive($now)) {
                return;
            }

            $repository->update($plan, $command->lockVersion);
            $repository->appendOperationalChange(new PlanOperationalChange(
                id: (string) Str::uuid7(),
                planId: $plan->id,
                action: PlanOperationalAction::Archive,
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
        });
    }
}
