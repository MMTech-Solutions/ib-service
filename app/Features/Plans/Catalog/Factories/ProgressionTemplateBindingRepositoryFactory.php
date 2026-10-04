<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Factories;

use App\Features\Plans\Catalog\Contracts\Repositories\ProgressionTemplateBindingRepositoryInterface;
use Illuminate\Contracts\Container\Container;

final class ProgressionTemplateBindingRepositoryFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(): ProgressionTemplateBindingRepositoryInterface
    {
        return $this->container->make('plans.progression-template-bindings.repositories.postgresql');
    }
}
