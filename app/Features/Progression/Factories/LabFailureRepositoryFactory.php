<?php

declare(strict_types=1);

namespace App\Features\Progression\Factories;

use App\Features\Progression\Repositories\PostgreSql\PostgreSqlLabFailureRepository;
use Illuminate\Contracts\Container\Container;

final class LabFailureRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): PostgreSqlLabFailureRepository
    {
        return $this->container->make('progression.lab-failures.repository');
    }
}
