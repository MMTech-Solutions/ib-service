<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class TaskData extends Data
{
    public function __construct(public string $code, public string $description, public string $cron_expression, public bool $automatic_enabled = true, public int $version = 1, public ?string $updated_at = null) {}
}
