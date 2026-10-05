<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use App\Features\Programs\Contracts\Data\V1\CaptureProgressionLadderQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use App\Features\Programs\Contracts\Ports\Input\CaptureProgressionLadderPort;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionRecoveryAttemptData;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Models\ProgressionRunResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

final class PrepareProgressionRecoveryService
{
    /** @var array<string, ProgressionLadderData|null> */
    private array $ladders = [];

    /** @var array<string, ProgressionRecoveryAttemptData> */
    private array $attempts = [];

    public function __construct(private readonly CaptureProgressionLadderPort $ladder, private readonly PrepareProgressionRunService $preparation) {}

    public function begin(): void
    {
        $this->ladders = [];
        $this->attempts = [];
    }

    public function execute(ProgressionRunRepositoryInterface $repository, ProgressionRunResult $result, CarbonImmutable $now, string $stage): ProgressionRecoveryAttemptData
    {
        if (isset($this->attempts[$result->id])) {
            return $this->attempts[$result->id];
        }
        $run = $repository->findRun($result->runId) ?? throw new \LogicException('Run missing.');
        $snapshot = $repository->snapshot($run->id);
        $participant = $snapshot === null ? null : collect($snapshot->participants)->firstWhere('subscription_id', $result->subscriptionId);
        $points = $result->totalPoints?->value() ?? ($participant['total_points'] ?? null);
        if ($participant !== null && ! $participant['is_evaluable'] && $stage === 'finalize') {
            $attempt = new ProgressionRecoveryAttemptData((string) Str::uuid7(), $now->toISOString(), null, $points, null, $stage, 'skipped', null);
            $repository->saveRecoveryAttempt($result, $attempt, $now);

            return $this->attempts[$result->id] = $attempt;
        }
        $target = null;
        $failure = $points === null ? 'missing_points_evidence' : null;
        if (! array_key_exists($run->planId, $this->ladders)) {
            try {
                $this->ladders[$run->planId] = $this->ladder->execute(new CaptureProgressionLadderQueryData($run->planId));
            } catch (Throwable) {
                $this->ladders[$run->planId] = null;
            }
        }
        $ladder = $this->ladders[$run->planId];
        if ($failure === null) {
            try {
                $target = $ladder === null ? null : $this->preparation->target(new ProgressionRunSnapshotData($ladder, [], $now->toISOString()), $points);
                $failure = $target === null ? 'ladder_unavailable' : null;
            } catch (Throwable) {
                $failure = 'ladder_unavailable';
            }
        }
        $attempt = new ProgressionRecoveryAttemptData((string) Str::uuid7(), $now->toISOString(), $ladder, $points, $target, $stage, $failure === null ? 'prepared' : 'failed', $failure);
        $repository->saveRecoveryAttempt($result, $attempt, $now);

        return $this->attempts[$result->id] = $attempt;
    }
}
