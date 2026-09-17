<?php

declare(strict_types=1);

namespace App\Features\Progression\Factories;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ActivityEvaluationRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ActivityEvaluationRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('progression.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('progression.evaluations.repositories.memory'),
            'postgresql' => $this->container->make('progression.evaluations.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported progression repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
