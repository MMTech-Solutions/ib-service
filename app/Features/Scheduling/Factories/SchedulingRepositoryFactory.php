<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Factories;

use App\Features\Scheduling\Repositories\SchedulingRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SchedulingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): SchedulingRepositoryInterface
    {
        return match ($driver ?? config('scheduling.repository', 'postgresql')) {
            'postgresql' => $this->container->make('scheduling.repositories.postgresql'),
            'memory' => $this->container->make('scheduling.repositories.memory'),
            default => throw new InvalidArgumentException('Unsupported scheduling repository.'),
        };
    }
}
