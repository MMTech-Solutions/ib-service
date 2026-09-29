<?php

declare(strict_types=1);

namespace App\Features\Progression\Factories;

use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ProgressionRunRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ProgressionRunRepositoryInterface
    {
        return match ($driver ?? (string) config('progression.repository', 'postgresql')) {
            'memory' => $this->container->make('progression.runs.repositories.memory'),
            'postgresql' => $this->container->make('progression.runs.repositories.postgresql'),
            default => throw new InvalidArgumentException('Unsupported progression runs repository.'),
        };
    }
}
