<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionDetailData;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionNotFoundException;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ShowSubscriptionCommand;

final class ShowSubscriptionUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ShowSubscriptionCommand $command): SubscriptionDetailData
    {
        $subscription = $this->repositoryFactory->make()->findById($command->subscriptionId);
        if ($subscription === null) {
            throw SubscriptionNotFoundException::forId($command->subscriptionId);
        }

        return $this->presentSubscription->toDetail($subscription);
    }
}
