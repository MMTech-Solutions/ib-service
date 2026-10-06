<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Factories;

use App\Features\Rules\Assignments\Contracts\Repositories\CpaConfigurationRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class CpaConfigurationRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): CpaConfigurationRepositoryInterface
    {
        return match ($driver ?? (string) config('rules.repository', 'postgresql')) {
            'postgresql' => $this->container->make('rules.cpa.repositories.postgresql'),
            'memory' => $this->container->make('rules.cpa.repositories.memory'),
            default => throw new \InvalidArgumentException('Unsupported CPA configuration repository.'),
        };
    }
}
