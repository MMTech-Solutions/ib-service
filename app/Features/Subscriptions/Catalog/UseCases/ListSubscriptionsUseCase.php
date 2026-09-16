<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\PresentSubscriptionAction;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionsPageData;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionsCommand;
use App\Features\Subscriptions\Catalog\Models\Subscription;

final class ListSubscriptionsUseCase
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
        private readonly PresentSubscriptionAction $presentSubscription,
    ) {}

    public function execute(ListSubscriptionsCommand $command): SubscriptionsPageData
    {
        $page = $this->repositoryFactory->make()->paginate($command->toQueryData());

        return new SubscriptionsPageData(
            subscriptions: array_map(
                fn (Subscription $subscription) => $this->presentSubscription->toData($subscription),
                $page->subscriptions,
            ),
            currentPage: $page->currentPage,
            perPage: $page->perPage,
            total: $page->total,
            lastPage: $page->lastPage,
        );
    }
}
