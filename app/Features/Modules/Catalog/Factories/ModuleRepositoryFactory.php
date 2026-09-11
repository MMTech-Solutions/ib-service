<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ModuleRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ModuleRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('modules.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('modules.repositories.memory'),
            'postgresql' => $this->container->make('modules.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported modules repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
