<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

interface IsModuleReferencedPort
{
    public function isReferenced(string $moduleId): bool;
}
