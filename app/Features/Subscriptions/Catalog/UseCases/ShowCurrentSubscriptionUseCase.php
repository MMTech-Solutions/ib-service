<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionData;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotFoundException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ShowCurrentSubscriptionCommand;

final class ShowCurrentSubscriptionUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ShowCurrentSubscriptionCommand $command): SubscriptionData
    {
        $subscription = $this->repositoryFactory->make()->findOpenByExternalUserId($command->externalUserId);
        if ($subscription === null) {
            throw SubscriptionNotFoundException::openForUser();
        }

        return $this->presentSubscription->toData($subscription);
    }
}
