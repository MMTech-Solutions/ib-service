<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Repositories\RewardReadRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class RewardReadRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): RewardReadRepositoryInterface
    {
        return match ((string) config('rewards.repository', 'postgresql')) {
            'postgresql' => $this->container->make('rewards.read.repositories.postgresql'),
            default => throw new InvalidArgumentException('Unsupported reward read repository'),
        };
    }
}
