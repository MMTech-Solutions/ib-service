<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Enums;

enum RuleVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
