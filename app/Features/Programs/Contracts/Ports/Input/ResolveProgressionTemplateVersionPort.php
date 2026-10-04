<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ProgressionTemplateVersionIdentityData;

interface ResolveProgressionTemplateVersionPort
{
    public function execute(string $versionId): ?ProgressionTemplateVersionIdentityData;
}
