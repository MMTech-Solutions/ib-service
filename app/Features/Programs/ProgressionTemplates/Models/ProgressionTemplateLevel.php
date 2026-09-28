<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Models;

final class ProgressionTemplateLevel
{
    public function __construct(public string $id, public int $distributionLevel, public string $weight) {}
}
