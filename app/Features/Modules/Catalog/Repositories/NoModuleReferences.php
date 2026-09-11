<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;

final class NoModuleReferences implements ModuleReferenceGuardInterface
{
    public function isReferenced(string $moduleId): bool
    {
        return false;
    }
}
