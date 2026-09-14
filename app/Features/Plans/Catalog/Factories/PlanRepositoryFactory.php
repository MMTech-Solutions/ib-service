<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Factories;

use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class PlanRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): PlanRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('plans.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('plans.repositories.memory'),
            'postgresql' => $this->container->make('plans.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported plans repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
