<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveRewardBackfillStartData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveRewardBackfillStartPort;

final class ResolveRewardBackfillStartUseCase implements ResolveRewardBackfillStartPort
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositoryFactory) {}

    public function execute(): ResolveRewardBackfillStartData
    {
        return new ResolveRewardBackfillStartData(
            $this->repositoryFactory->make()->earliestActivatedAt(),
        );
    }
}
