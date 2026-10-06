<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaSourceProgressData extends Data
{
    public function __construct(public readonly string $kind, public readonly ?string $module_id, public readonly string $status, public readonly string $quantity, public readonly string $points, public readonly ?string $observed_until, public readonly ?string $last_evaluated_at, public readonly ?string $last_error_code) {}
}
