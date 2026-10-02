<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Factories;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramVolumeRewardConfigurationRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ProgramVolumeRewardConfigurationRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): ProgramVolumeRewardConfigurationRepositoryInterface
    {
        if ((string) config('programs.repository', 'postgresql') !== 'postgresql') {
            throw new InvalidArgumentException('Volume reward configuration requires PostgreSQL.');
        }

        return $this->container->make('programs.volume-reward-configurations.repositories.postgresql');
    }
}
