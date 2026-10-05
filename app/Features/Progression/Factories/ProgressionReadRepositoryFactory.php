<?php

declare(strict_types=1);

namespace App\Features\Progression\Factories;

use App\Features\Progression\Contracts\Repositories\ProgressionReadRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class ProgressionReadRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): ProgressionReadRepositoryInterface
    {
        return $this->container->make('progression.read.repository');
    }
}
