<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionHistoryResultData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionPlacementHistoryData;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Http\V1\Commands\ListSubscriptionPlacementsCommand;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;

final class ListSubscriptionPlacementsUseCase
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositoryFactory) {}

    public function execute(ListSubscriptionPlacementsCommand $command): SubscriptionHistoryResultData
    {
        $page = $this->repositoryFactory->make()->paginatePlacements($command->query);
        $entries = array_map(static fn (SubscriptionPlacement $placement): SubscriptionPlacementHistoryData => new SubscriptionPlacementHistoryData(
            $placement->id, $placement->subscriptionId, $placement->programId, $placement->isFixed(), $placement->effectiveFrom, $placement->effectiveUntil,
        ), $page->entries);

        return new SubscriptionHistoryResultData($entries, $page->total);
    }
}
