<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Factories;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\NegativePnlSubscriptionRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class NegativePnlSubscriptionRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): NegativePnlSubscriptionRepositoryInterface
    {
        return $this->container->make('subscriptions.negative-pnl.repositories.postgresql');
    }
}
