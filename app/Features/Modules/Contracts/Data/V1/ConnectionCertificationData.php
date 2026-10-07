<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ConnectionCertificationData extends Data
{
    public function __construct(public readonly string $provider, public readonly ?string $module_id, public readonly bool $certified, public readonly string $code, public readonly string $checked_at, public readonly int $duration_ms) {}
}
