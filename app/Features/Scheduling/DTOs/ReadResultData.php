<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class ReadResultData extends Data
{
    /** @param array<string, mixed>|list<array<string, mixed>> $payload */
    public function __construct(public readonly array $payload, public readonly ?int $total = null) {}
}
