<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

interface ResolveCpaEvidenceCapabilityPort
{
    public function execute(string $moduleCode): bool;
}
