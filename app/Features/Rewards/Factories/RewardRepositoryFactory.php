<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class RewardRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): RewardRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('rewards.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'postgresql' => $this->container->make('rewards.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported rewards repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
