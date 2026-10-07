<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class RunData extends Data
{
    public function __construct(
        public string $id, public string $task_code, public string $origin, public TaskData $configuration,
        public string $accepted_at, public string $status = 'queued', public ?string $scheduled_at = null,
        public ?string $actor_id = null, public ?string $reason = null, public ?string $idempotency_key = null,
        public ?string $request_hash = null, public ?string $started_at = null, public ?string $heartbeat_at = null,
        public ?string $finished_at = null, public ?int $exit_code = null, public ?string $outcome = null,
        public string $stdout = '', public string $stderr = '', public bool $stdout_truncated = false,
        public bool $stderr_truncated = false, public int $timeout_seconds = 3600,
    ) {}
}
