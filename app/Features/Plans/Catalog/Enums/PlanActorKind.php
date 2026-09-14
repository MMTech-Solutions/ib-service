<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Enums;

enum PlanActorKind: string
{
    case Iam = 'iam';
    case System = 'system';
}
