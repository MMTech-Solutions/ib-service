<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Events\V1;

final class CpaContextExpired
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $cpaContextId,
        public readonly string $referredUserId,
        public readonly string $ibUserId,
        public readonly string $planId,
        public readonly string $programId,
        public readonly string $ruleId,
        public readonly string $ruleVersionId,
        public readonly string $capturedAt,
        public readonly int $expirationDays,
        public readonly int $daysElapsed,
        public readonly string $reason,
        public readonly string $occurredAt,
    ) {}
}
