<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Repositories\NegativePnlProcessingRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class NegativePnlProcessingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): NegativePnlProcessingRepositoryInterface
    {
        return $this->container->make('rewards.negative-pnl-processing.repositories.postgresql');
    }
}
