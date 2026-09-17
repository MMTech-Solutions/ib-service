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
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotFoundException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotPendingException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ApproveSubscriptionCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ApproveSubscriptionUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanSubscriptionContextPort $planContext,
        private readonly ResolveProgramSubscriptionContextPort $programContext,
        private readonly LockPlanRowsPort $lockPlanRows,
        private readonly AssertPlanEligibleForSubscriptionAction $assertPlanEligible,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ApproveSubscriptionCommand $command): SubscriptionDetailData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): SubscriptionDetailData {
            $subscription = $repository->findById($command->subscriptionId);
            if ($subscription === null) {
                throw SubscriptionNotFoundException::forId($command->subscriptionId);
            }

            if ($subscription->status !== SubscriptionStatus::Pending) {
                throw SubscriptionNotPendingException::forId($subscription->id);
            }

            $this->lockPlanRows->lockAscending([$subscription->planId]);

            $subscription = $repository->findById($command->subscriptionId);
            if ($subscription === null) {
                throw SubscriptionNotFoundException::forId($command->subscriptionId);
            }

            if ($subscription->status !== SubscriptionStatus::Pending) {
                throw SubscriptionNotPendingException::forId($subscription->id);
            }

            $plan = $this->planContext->resolve(new ResolvePlanSubscriptionContextQueryData(
                plan_id: $subscription->planId,
            ));
            $this->assertPlanEligible->assert($plan);

            $program = $command->programId === null
                ? $this->programContext->resolveFirstByPosition(
                    new ResolveFirstProgramByPositionQueryData(plan_id: $subscription->planId),
                )
                : $this->programContext->assertBelongsToPlan(
                    new AssertProgramBelongsToPlanQueryData(
                        plan_id: $subscription->planId,
                        program_id: $command->programId,
                    ),
                );

            $expectedLockVersion = $subscription->lockVersion;
            $generateId = static fn (): string => (string) Str::uuid7();
            $subscription->approve(
                programId: $program->id,
                placementId: $generateId(),
                operationId: $generateId(),
                actorKind: SubscriptionActorKind::Iam,
                actorExternalUserId: $command->actorExternalUserId,
                reason: $command->reason,
                generateId: $generateId,
                now: CarbonImmutable::now('UTC')->toISOString(),
            );

            $repository->save($subscription, $expectedLockVersion);

            return $this->presentSubscription->toDetail($subscription);
        });
    }
}
