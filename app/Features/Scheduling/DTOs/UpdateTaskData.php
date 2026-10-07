<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class UpdateTaskData extends Data
{
    public function __construct(public readonly string $code, public readonly int $version, public readonly string $actor_id, public readonly string $reason, public readonly ?string $description = null, public readonly ?string $cron_expression = null, public readonly ?bool $automatic_enabled = null) {}
}
