<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Enums;

enum OperationalControlAction: string
{
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Pause = 'pause';
    case Resume = 'resume';
}
