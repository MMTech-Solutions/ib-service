<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class PageData extends Data
{
    /** @param list<TaskData|RunData|AuditData> $items */
    public function __construct(public array $items, public int $total) {}
}
