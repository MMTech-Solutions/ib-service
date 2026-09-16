<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Data\V1\ResolveFirstProgramByPositionQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Subscriptions\Catalog\Actions\AssertPlanEligibleForSubscriptionAction;
use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionData;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateOpenSubscriptionException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ApplyForSubscriptionCommand;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ApplyForSubscriptionUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanSubscriptionContextPort $planContext,
        private readonly ResolveProgramSubscriptionContextPort $programContext,
        private readonly AssertPlanEligibleForSubscriptionAction $assertPlanEligible,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ApplyForSubscriptionCommand $command): SubscriptionData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): SubscriptionData {
            if ($repository->findOpenByExternalUserId($command->externalUserId) !== null) {
                throw DuplicateOpenSubscriptionException::forUser($command->externalUserId);
            }

            $plan = $this->planContext->resolve(new ResolvePlanSubscriptionContextQueryData(
                plan_id: $command->planId,
            ));
            $this->assertPlanEligible->assert($plan);

            $now = CarbonImmutable::now('UTC')->toISOString();
            $generateId = static fn (): string => (string) Str::uuid7();
            $operationId = $generateId();

            if ($plan->requires_approval) {
                $subscription = Subscription::requestPending(
                    id: $generateId(),
                    externalUserId: $command->externalUserId,
                    planId: $plan->id,
                    requiresApproval: true,
                    operationId: $operationId,
                    actorKind: SubscriptionActorKind::Iam,
                    actorExternalUserId: $command->actorExternalUserId,
                    reason: $command->reason,
                    generateId: $generateId,
                    now: $now,
                );
            } else {
                $program = $this->programContext->resolveFirstByPosition(
                    new ResolveFirstProgramByPositionQueryData(plan_id: $plan->id),
                );

                $subscription = Subscription::requestActive(
                    id: $generateId(),
                    externalUserId: $command->externalUserId,
                    planId: $plan->id,
                    programId: $program->id,
                    placementId: $generateId(),
                    operationId: $operationId,
                    actorKind: SubscriptionActorKind::Iam,
                    actorExternalUserId: $command->actorExternalUserId,
                    reason: $command->reason,
                    generateId: $generateId,
                    now: $now,
                );
            }

            $repository->create($subscription);

            return $this->presentSubscription->toData($subscription);
        });
    }
}
