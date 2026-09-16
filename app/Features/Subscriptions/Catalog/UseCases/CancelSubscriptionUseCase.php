<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionDetailData;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionConcurrencyException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotActiveException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotFoundException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\CancelSubscriptionCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class CancelSubscriptionUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(CancelSubscriptionCommand $command): SubscriptionDetailData
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

            $generateId = static fn (): string => (string) Str::uuid7();
            $subscription->cancel(
                operationId: $generateId(),
                actorKind: SubscriptionActorKind::Iam,
                actorExternalUserId: $command->actorExternalUserId,
                reason: $command->reason,
                generateId: $generateId,
                now: CarbonImmutable::now('UTC')->toISOString(),
            );

            $repository->save($subscription, $command->lockVersion);

            return $this->presentSubscription->toDetail($subscription);
        });
    }
}
