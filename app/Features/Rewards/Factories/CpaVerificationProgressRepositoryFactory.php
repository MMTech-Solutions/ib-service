<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Repositories\CpaVerificationProgressRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class CpaVerificationProgressRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): CpaVerificationProgressRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('rewards.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'postgresql' => $this->container->make('rewards.cpa_progress.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported rewards progress repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
