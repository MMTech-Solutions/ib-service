<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\Contracts\Data\V1\RewardUplineBeneficiaryData;
use App\Features\Rewards\DTOs\PersistVolumeRewardData;
use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\DTOs\VolumeRewardEvaluationData;
use App\Features\Rewards\DTOs\VolumeRewardPreparedInputsData;
use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlVolumeRewardProcessingRepository implements VolumeRewardProcessingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function claimEvaluation(VolumeRewardActivityData $activity, CarbonImmutable $now, CarbonImmutable $expiresAt): ?VolumeRewardEvaluationData
    {
        return $this->connection->transaction(function () use ($activity, $now, $expiresAt) {
            $this->connection->table('volume_reward_evaluations')->insertOrIgnore(['id' => (string) Str::uuid7(), 'module_id' => $activity->module_id, 'source_activity_id' => $activity->source_activity_id, 'activity' => json_encode($activity->toArray(), JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            $row = $this->connection->table('volume_reward_evaluations')->where('module_id', $activity->module_id)->where('source_activity_id', $activity->source_activity_id)->lockForUpdate()->firstOrFail();
            if ($row->lease_expires_at !== null && CarbonImmutable::parse($row->lease_expires_at)->greaterThan($now)) {
                return null;
            }
            $frozenActivity = json_decode($row->activity, true, 512, JSON_THROW_ON_ERROR);
            if (VolumeRewardActivityData::from($frozenActivity)->toArray() !== $activity->toArray()) {
                throw new InvalidVolumeRewardActivityException('VOLUME_ACTIVITY_CONFLICT', 'Volume activity conflicts with frozen evidence.', 422);
            }
            $token = (string) Str::uuid7();
            $this->connection->table('volume_reward_evaluations')->where('id', $row->id)->update(['lease_token' => $token, 'lease_expires_at' => $expiresAt, 'updated_at' => $now]);
            $distribution = null;
            if ($row->distribution !== null) {
                $data = json_decode($row->distribution, true, 512, JSON_THROW_ON_ERROR);
                $distribution = ResolveRewardUplineResultData::resolved(array_map(fn ($item) => RewardUplineBeneficiaryData::from($item), $data['beneficiaries']), $data['resolved_at']);
            }
            $preparations = [];
            foreach (json_decode($row->preparations, true, 512, JSON_THROW_ON_ERROR) as $channel => $data) {
                $preparations[$channel] = new VolumeRewardPreparedInputsData(array_map(fn ($item) => PersistVolumeRewardData::from($item), $data['rewards']), $data['skipped'], $data['outcomes']);
            }

            return new VolumeRewardEvaluationData($row->id, $token, VolumeRewardActivityData::from(json_decode($row->activity, true, 512, JSON_THROW_ON_ERROR)), $distribution, $preparations);
        });
    }

    public function freezeDistribution(VolumeRewardEvaluationData $evaluation, ResolveRewardUplineResultData $distribution): void
    {
        $this->evaluationTransaction($evaluation, function () use ($evaluation, $distribution): void {
            $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->whereNull('distribution')->update(['distribution' => json_encode($distribution->toArray(), JSON_THROW_ON_ERROR)]);
        });
    }

    public function freezePreparation(VolumeRewardEvaluationData $evaluation, string $channel, VolumeRewardPreparedInputsData $inputs): void
    {
        $this->evaluationTransaction($evaluation, function () use ($evaluation, $channel, $inputs): void {
            $row = $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->firstOrFail();
            $preparations = json_decode($row->preparations, true, 512, JSON_THROW_ON_ERROR);
            if (! array_key_exists($channel, $preparations)) {
                $preparations[$channel] = $inputs->toArray();
                $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->update(['preparations' => json_encode($preparations, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function recordEvaluationOutcome(VolumeRewardEvaluationData $evaluation, string $key, string $outcome): void
    {
        $this->evaluationTransaction($evaluation, function () use ($evaluation, $key, $outcome): void {
            $outcomes = json_decode($this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->value('outcomes'), true, 512, JSON_THROW_ON_ERROR);
            $outcomes[$key] ??= $outcome;
            $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->update(['outcomes' => json_encode($outcomes, JSON_THROW_ON_ERROR)]);
        });
    }

    public function evaluationTransaction(VolumeRewardEvaluationData $evaluation, \Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($evaluation, $callback): mixed {
            $row = $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->lockForUpdate()->firstOrFail();
            if ($row->lease_token !== $evaluation->lease_token || $row->lease_expires_at === null || CarbonImmutable::parse($row->lease_expires_at)->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw new \RuntimeException('volume_evaluation_lease_lost');
            }
            $result = $callback();
            if (CarbonImmutable::parse($row->lease_expires_at)->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw new \RuntimeException('volume_evaluation_lease_lost');
            }

            return $result;
        });
    }

    public function releaseEvaluation(VolumeRewardEvaluationData $evaluation): void
    {
        $this->connection->table('volume_reward_evaluations')->where('id', $evaluation->id)->where('lease_token', $evaluation->lease_token)->update(['lease_token' => null, 'lease_expires_at' => null]);
    }

    public function recordEvent(RecordVolumeRewardEventData $data): void
    {
        $now = now('UTC')->toIso8601String();
        $this->connection->transaction(function () use ($data, $now): void {
            $activity = $data->activity->toArray();
            $this->connection->table('volume_reward_event_receipts')->insertOrIgnore([
                'id' => (string) Str::uuid7(), 'module_id' => $data->activity->module_id, 'source_activity_id' => $data->activity->source_activity_id,
                'event_id' => $data->event_id, 'schema_version' => $data->schema_version, 'activity' => json_encode($activity, JSON_THROW_ON_ERROR),
                'status' => 'pending', 'attempt_count' => 0, 'next_attempt_at' => $now,
                'transport_snapshot' => json_encode($data->transport_snapshot, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $row = $this->connection->table('volume_reward_event_receipts')->where('module_id', $data->activity->module_id)->where('source_activity_id', $data->activity->source_activity_id)->lockForUpdate()->firstOrFail();
            if (VolumeRewardActivityData::from(json_decode($row->activity, true, 512, JSON_THROW_ON_ERROR))->toArray() !== $activity) {
                $this->connection->table('volume_reward_event_receipts')->where('id', $row->id)->update([
                    'conflict_snapshot' => json_encode(['event_id' => $data->event_id, 'activity' => $activity, 'transport' => $data->transport_snapshot], JSON_THROW_ON_ERROR),
                    'last_error_code' => 'VOLUME_ACTIVITY_CONFLICT',
                ]);
            }
        });
    }

    public function claimNextEvent(CarbonImmutable $now, CarbonImmutable $leaseExpiresAt): ?object
    {
        return $this->connection->transaction(function () use ($now, $leaseExpiresAt): ?object {
            $receipt = $this->connection->table('volume_reward_event_receipts')
                ->where(function ($query) use ($now): void {
                    $query->where(function ($ready) use ($now): void {
                        $ready->whereIn('status', ['pending', 'retryable'])
                            ->where(fn ($attempt) => $attempt->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now));
                    })->orWhere(function ($expired) use ($now): void {
                        $expired->where('status', 'processing')->where('claim_expires_at', '<=', $now);
                    });
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();
            if ($receipt === null) {
                return null;
            }

            $token = (string) Str::uuid7();
            $this->connection->table('volume_reward_event_receipts')->where('id', $receipt->id)->update([
                'status' => 'processing',
                'claim_token' => $token,
                'claim_expires_at' => $leaseExpiresAt,
                'attempt_count' => (int) $receipt->attempt_count + 1,
                'updated_at' => $now,
            ]);
            $receipt->claim_token = $token;
            $receipt->attempt_count = (int) $receipt->attempt_count + 1;

            return $receipt;
        });
    }

    public function markEventProcessed(string $id, string $claimToken, CarbonImmutable $at): void
    {
        $this->finishEvent($id, $claimToken, ['status' => 'processed', 'resolved_at' => $at, 'last_error_code' => null], $at);
    }

    public function markEventRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $nextAttemptAt): void
    {
        $this->finishEvent($id, $claimToken, ['status' => 'retryable', 'next_attempt_at' => $nextAttemptAt, 'last_error_code' => $errorCode], $nextAttemptAt);
    }

    public function markEventRejected(string $id, string $claimToken, string $errorCode, CarbonImmutable $at): void
    {
        $this->finishEvent($id, $claimToken, ['status' => 'rejected', 'resolved_at' => $at, 'last_error_code' => $errorCode], $at);
    }

    public function claimPeriodicRun(string $moduleId, ?string $backfillFrom, CarbonImmutable $until, CarbonImmutable $leaseExpiresAt): ?object
    {
        return $this->connection->transaction(function () use ($moduleId, $backfillFrom, $until, $leaseExpiresAt): ?object {
            $run = $this->connection->table('volume_reward_runs')
                ->where('module_id', $moduleId)
                ->where(function ($query) use ($until): void {
                    $query->where('status', 'pending')
                        ->orWhere(function ($retryable) use ($until): void {
                            $retryable->where('status', 'retryable')
                                ->where(fn ($attempt) => $attempt->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $until));
                        })
                        ->orWhere(fn ($expired) => $expired->where('status', 'processing')->where('claim_expires_at', '<=', $until));
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                $from = $this->connection->table('volume_reward_runs')
                    ->where('module_id', $moduleId)
                    ->where('status', 'completed')
                    ->max('occurred_until') ?? $backfillFrom;
                if ($from === null || ! CarbonImmutable::parse((string) $from)->utc()->lt($until)) {
                    return null;
                }

                $id = (string) Str::uuid7();
                $this->connection->table('volume_reward_runs')->insert([
                    'id' => $id,
                    'module_id' => $moduleId,
                    'occurred_from' => $from,
                    'occurred_until' => $until,
                    'cursor' => null,
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'next_attempt_at' => null,
                    'created_at' => $until,
                    'updated_at' => $until,
                ]);
                $run = $this->connection->table('volume_reward_runs')->where('id', $id)->lockForUpdate()->first();
            }

            if ($run === null) {
                return null;
            }

            $token = (string) Str::uuid7();
            $this->connection->table('volume_reward_runs')->where('id', $run->id)->update([
                'status' => 'processing',
                'claim_token' => $token,
                'claim_expires_at' => $leaseExpiresAt,
                'attempt_count' => (int) $run->attempt_count + 1,
                'updated_at' => $until,
            ]);
            $run->claim_token = $token;
            $run->attempt_count = (int) $run->attempt_count + 1;

            return $run;
        });
    }

    public function completePeriodicPage(string $id, string $claimToken, ?string $nextCursor, CarbonImmutable $at): void
    {
        $this->connection->table('volume_reward_runs')
            ->where('id', $id)
            ->where('claim_token', $claimToken)
            ->update([
                'cursor' => $nextCursor,
                'status' => $nextCursor === null ? 'completed' : 'pending',
                'claim_token' => null,
                'claim_expires_at' => null,
                'last_error_code' => null,
                'next_attempt_at' => null,
                'updated_at' => $at,
            ]);
    }

    public function markPeriodicRunRetryable(string $id, string $claimToken, string $errorCode, CarbonImmutable $at, CarbonImmutable $nextAttemptAt): void
    {
        $this->connection->table('volume_reward_runs')
            ->where('id', $id)
            ->where('claim_token', $claimToken)
            ->update([
                'status' => 'retryable',
                'claim_token' => null,
                'claim_expires_at' => null,
                'last_error_code' => $errorCode,
                'next_attempt_at' => $nextAttemptAt,
                'updated_at' => $at,
            ]);
    }

    /** @param array<string, mixed> $updates */
    private function finishEvent(string $id, string $claimToken, array $updates, CarbonImmutable $at): void
    {
        $this->connection->table('volume_reward_event_receipts')
            ->where('id', $id)
            ->where('claim_token', $claimToken)
            ->update([...$updates, 'claim_token' => null, 'claim_expires_at' => null, 'updated_at' => $at]);
    }
}
