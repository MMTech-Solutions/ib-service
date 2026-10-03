<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Factories\NegativePnlSubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionSegmentsPort;

final class ListNegativePnlSubscriptionSegmentsUseCase implements ListNegativePnlSubscriptionSegmentsPort
{
    public function __construct(private readonly NegativePnlSubscriptionRepositoryFactory $repositories) {}

    public function execute(string $subscriptionId, string $from, string $until): array
    {
        return $this->repositories->make()->segments($subscriptionId, $from, $until);
    }
}
