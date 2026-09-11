<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\ValueObjects;

enum ProcessingStatus: string
{
    case Running = 'running';
    case Paused = 'paused';
}
