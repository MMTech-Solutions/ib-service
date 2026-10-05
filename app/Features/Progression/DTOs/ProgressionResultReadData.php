<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionResultReadData extends Data
{
    /**
     * @param array{total_points: ?string, target_program_id: ?string, decision_at: ?string}|null $original_decision
     * @param list<array{id: string, attempted_at: string, ladder: ?array{programs: list<array{program_id: string, position: int, entry_threshold: string}>}, total_points: ?string, target_program_id: ?string, stage: string, outcome: string, failure_code: ?string}> $recovery_attempts
     * @param array{status: string, outcome: ?string, failure_code: ?string, attempt_count: int, last_attempt_at: ?string, applied_at: ?string} $placement */
    public function __construct(public readonly string $id, public readonly string $run_id, public readonly string $subscription_id, public readonly string $status, public readonly ?string $total_points, public readonly ?string $target_program_id, public readonly int $attempt_count, public readonly ?string $failure_code, public readonly ?string $decision_at, public readonly ?string $completed_at, public readonly ?bool $is_evaluable, public readonly ?string $omission_reason, public readonly array $placement, public readonly ?array $original_decision = null, public readonly array $recovery_attempts = []) {}
}
