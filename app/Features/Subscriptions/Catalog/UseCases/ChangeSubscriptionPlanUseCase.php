<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\LockPlanRowsPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveFirstProgramByPositionQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Subscriptions\Catalog\Actions\AssertPlanEligibleForSubscriptionAction;
use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionDetailData;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionConcurrencyException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotActiveException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotFoundException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ChangeSubscriptionPlanCommand;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ChangeSubscriptionPlanUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanSubscriptionContextPort $planContext,
        private readonly ResolveProgramSubscriptionContextPort $programContext,
        private readonly LockPlanRowsPort $lockPlanRows,
        private readonly AssertPlanEligibleForSubscriptionAction $assertPlanEligible,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ChangeSubscriptionPlanCommand $command): SubscriptionDetailData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): SubscriptionDetailData {
            $subscription = $repository->findById($command->subscriptionId);
            if ($subscription === null) {
                throw SubscriptionNotFoundException::forId($command->subscriptionId);
            }

            if ($subscription->status !== SubscriptionStatus::Active) {
                throw SubscriptionNotActiveException::forId($subscription->id);
            }

            if ($subscription->lockVersion !== $command->lockVersion) {
                throw SubscriptionConcurrencyException::forSubscription($subscription->id);
            }

            $this->lockPlanRows->lockAscending([
                $subscription->planId,
                $command->planId,
            ]);

            $destinationPlan = $this->planContext->resolve(new ResolvePlanSubscriptionContextQueryData(
                plan_id: $command->planId,
            ));
            $this->assertPlanEligible->assert($destinationPlan);

            $program = $command->programId === null
                ? $this->programContext->resolveFirstByPosition(
                    new ResolveFirstProgramByPositionQueryData(plan_id: $destinationPlan->id),
                )
                : $this->programContext->assertBelongsToPlan(
                    new AssertProgramBelongsToPlanQueryData(
                        plan_id: $destinationPlan->id,
                        program_id: $command->programId,
                    ),
                );

            $subscription = $repository->findById($command->subscriptionId);
            if ($subscription === null) {
                throw SubscriptionNotFoundException::forId($command->subscriptionId);
            }

            if ($subscription->status !== SubscriptionStatus::Active) {
                throw SubscriptionNotActiveException::forId($subscription->id);
            }

            if ($subscription->lockVersion !== $command->lockVersion) {
                throw SubscriptionConcurrencyException::forSubscription($subscription->id);
            }

            $generateId = static fn (): string => (string) Str::uuid7();
            $operationId = $generateId();
            $now = CarbonImmutable::now('UTC')->toISOString();

            $subscription->endForPlanChange(
                operationId: $operationId,
                actorKind: SubscriptionActorKind::Iam,
                actorExternalUserId: $command->actorExternalUserId,
                reason: $command->reason,
                generateId: $generateId,
                now: $now,
            );

            $incoming = Subscription::createFromPlanChange(
                id: $generateId(),
                externalUserId: $subscription->externalUserId,
                planId: $destinationPlan->id,
                replacesSubscriptionId: $subscription->id,
                programId: $program->id,
                placementId: $generateId(),
                operationId: $operationId,
                actorKind: SubscriptionActorKind::Iam,
                actorExternalUserId: $command->actorExternalUserId,
                reason: $command->reason,
                generateId: $generateId,
                now: $now,
            );

            $repository->replace($subscription, $command->lockVersion, $incoming);

            return $this->presentSubscription->toDetail($incoming);
        });
    }
}
