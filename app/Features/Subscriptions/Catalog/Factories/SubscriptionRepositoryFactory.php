<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Factories;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SubscriptionRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): SubscriptionRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('subscriptions.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('subscriptions.repositories.memory'),
            'postgresql' => $this->container->make('subscriptions.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported subscriptions repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
