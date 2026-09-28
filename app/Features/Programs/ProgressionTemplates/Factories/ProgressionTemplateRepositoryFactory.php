<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Factories;

use App\Features\Programs\ProgressionTemplates\Contracts\Repositories\ProgressionTemplateRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ProgressionTemplateRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(?string $driver = null): ProgressionTemplateRepositoryInterface
    {
        return match ($driver ?? config('progression-templates.repository', 'postgresql')) {
            'memory' => $this->container->make('programs.progression-templates.repositories.memory'),
            'postgresql' => $this->container->make('programs.progression-templates.repositories.postgresql'),
            default => throw new InvalidArgumentException('Unsupported progression templates repository.'),
        };
    }
}
