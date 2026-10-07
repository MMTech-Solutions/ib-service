<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class ReadQueryData extends Data
{
    public function __construct(public readonly string $resource, public readonly ?string $code = null, public readonly ?string $id = null, public readonly int $page = 1, public readonly int $per_page = 100, public readonly ?string $status = null, public readonly ?string $origin = null, public readonly ?string $from = null, public readonly ?string $until = null) {}
}
