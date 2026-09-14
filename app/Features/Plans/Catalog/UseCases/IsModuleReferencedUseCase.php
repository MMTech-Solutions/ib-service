<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Ports\Input\IsModuleReferencedPort;

final class IsModuleReferencedUseCase implements IsModuleReferencedPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function isReferenced(string $moduleId): bool
    {
        return $this->repositoryFactory->make()->isModuleReferenced($moduleId);
    }
}
