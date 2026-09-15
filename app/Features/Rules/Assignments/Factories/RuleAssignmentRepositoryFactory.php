<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Factories;

use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class RuleAssignmentRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): RuleAssignmentRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('rules.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('rules.assignments.repositories.memory'),
            'postgresql' => $this->container->make('rules.assignments.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported rule assignments repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
