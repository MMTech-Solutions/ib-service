<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use Carbon\CarbonImmutable;

interface VolumeRewardProcessingRepositoryInterface
{
    public function recordEvent(RecordVolumeRewardEventData $data): void;

    public function claimNextEvent(CarbonImmutable $now, CarbonImmutable $leaseExpiresAt): ?object;

    public function markEventProcessed(string $id, string $claimToken, CarbonImmutable $at): void;

    public function markEventRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $nextAttemptAt): void;

    public function markEventRejected(string $id, string $claimToken, string $errorCode, CarbonImmutable $at): void;

    public function claimPeriodicRun(string $moduleId, ?string $backfillFrom, CarbonImmutable $until, CarbonImmutable $leaseExpiresAt): ?object;

    public function completePeriodicPage(string $id, string $claimToken, ?string $nextCursor, CarbonImmutable $at): void;

    public function markPeriodicRunRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $at, CarbonImmutable $nextAttemptAt): void;
}
