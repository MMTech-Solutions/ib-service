<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Services\Adapters;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Plans\Contracts\Ports\Input\IsModuleReferencedPort;

final class PlanModuleReferenceGuard implements ModuleReferenceGuardInterface
{
    public function __construct(private readonly IsModuleReferencedPort $port) {}

    public function isReferenced(string $moduleId): bool
    {
        return $this->port->isReferenced($moduleId);
    }
}
