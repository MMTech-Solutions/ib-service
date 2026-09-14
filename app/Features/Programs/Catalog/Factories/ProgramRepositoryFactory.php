<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Factories;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ProgramRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ProgramRepositoryInterface
    {
        $selectedDriver = $driver ?? (string) config('programs.repository', 'postgresql');
        $repository = match ($selectedDriver) {
            'memory' => $this->container->make('programs.repositories.memory'),
            'postgresql' => $this->container->make('programs.repositories.postgresql'),
            default => throw new InvalidArgumentException("Unsupported programs repository [{$selectedDriver}]."),
        };

        return $repository;
    }
}
