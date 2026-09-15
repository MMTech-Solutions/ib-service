<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Factories;

use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class RuleRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): RuleRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('rules.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('rules.repositories.memory'),
            'postgresql' => $this->container->make('rules.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported rules repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
