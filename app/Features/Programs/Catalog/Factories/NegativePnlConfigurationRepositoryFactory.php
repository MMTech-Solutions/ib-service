<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Factories;

use App\Features\Programs\Catalog\Contracts\Repositories\NegativePnlConfigurationRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class NegativePnlConfigurationRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): NegativePnlConfigurationRepositoryInterface
    {
        return $this->container->make('programs.negative-pnl-configurations.repositories.postgresql');
    }
}
