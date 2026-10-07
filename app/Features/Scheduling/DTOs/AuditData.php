<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class AuditData extends Data
{
    public function __construct(public string $id, public string $task_code, public string $actor_id, public string $reason, public string $occurred_at, public TaskData $before, public TaskData $after) {}
}
