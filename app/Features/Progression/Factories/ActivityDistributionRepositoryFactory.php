<?php

declare(strict_types=1);

namespace App\Features\Progression\Factories;

use App\Features\Progression\Contracts\Repositories\ActivityDistributionRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ActivityDistributionRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ActivityDistributionRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('progression.repository', 'postgresql');

        return match ($selectedDriver) {
            'memory' => $this->container->make('progression.distributions.repositories.memory'),
            'postgresql' => $this->container->make('progression.distributions.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported progression repository [{$selectedDriver}]."),
        };
    }
}
