<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Repositories;

interface ModuleReferenceGuardInterface
{
    public function isReferenced(string $moduleId): bool;
}
