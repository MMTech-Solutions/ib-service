<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Factories\NegativePnlSubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionsPort;

final class ListNegativePnlSubscriptionsUseCase implements ListNegativePnlSubscriptionsPort
{
    public function __construct(private readonly NegativePnlSubscriptionRepositoryFactory $repositories) {}

    public function execute(string $programId, string $startsAt, ?string $endsAt, ?string $afterId, int $limit): array
    {
        return $this->repositories->make()->list($programId, $startsAt, $endsAt, $afterId, max(1, min($limit, 1000)));
    }
}
