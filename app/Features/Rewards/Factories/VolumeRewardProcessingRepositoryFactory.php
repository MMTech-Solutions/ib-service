<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class VolumeRewardProcessingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): VolumeRewardProcessingRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('rewards.repository', 'postgresql');

        return match ($selectedDriver) {
            'postgresql' => $this->container->make('rewards.volume-processing.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported volume reward processing repository [{$selectedDriver}]."),
        };
    }
}
