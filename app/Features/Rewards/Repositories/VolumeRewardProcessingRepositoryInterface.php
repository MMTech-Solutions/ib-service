<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\DTOs\VolumeRewardEvaluationData;
use App\Features\Rewards\DTOs\VolumeRewardPreparedInputsData;
use Carbon\CarbonImmutable;

interface VolumeRewardProcessingRepositoryInterface
{
    public function claimEvaluation(VolumeRewardActivityData $activity, CarbonImmutable $now, CarbonImmutable $expiresAt): ?VolumeRewardEvaluationData;

    public function freezeDistribution(VolumeRewardEvaluationData $evaluation, ResolveRewardUplineResultData $distribution): void;

    public function freezePreparation(VolumeRewardEvaluationData $evaluation, string $channel, VolumeRewardPreparedInputsData $inputs): void;

    public function recordEvaluationOutcome(VolumeRewardEvaluationData $evaluation, string $key, string $outcome): void;

    public function evaluationTransaction(VolumeRewardEvaluationData $evaluation, \Closure $callback): mixed;

    public function releaseEvaluation(VolumeRewardEvaluationData $evaluation): void;

    public function recordEvent(RecordVolumeRewardEventData $data): void;

    public function claimNextEvent(CarbonImmutable $now, CarbonImmutable $leaseExpiresAt): ?object;

    public function markEventProcessed(string $id, string $claimToken, CarbonImmutable $at): void;

    public function markEventRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $nextAttemptAt): void;

    public function markEventRejected(string $id, string $claimToken, string $errorCode, CarbonImmutable $at): void;

    public function claimPeriodicRun(string $moduleId, ?string $backfillFrom, CarbonImmutable $until, CarbonImmutable $leaseExpiresAt): ?object;

    public function completePeriodicPage(string $id, string $claimToken, ?string $nextCursor, CarbonImmutable $at): void;

    public function markPeriodicRunRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $at, CarbonImmutable $nextAttemptAt): void;
}
