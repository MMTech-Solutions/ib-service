<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlVolumeRewardProcessingRepository implements VolumeRewardProcessingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function recordEvent(RecordVolumeRewardEventData $data): void
    {
        $now = now('UTC')->toIso8601String();
        $this->connection->table('volume_reward_event_receipts')->upsert([
            ['id' => (string) Str::uuid7(), 'module_id' => $data->module_id, 'order_id' => $data->order_id, 'external_trader_id' => $data->external_trader_id, 'status' => 'pending', 'attempt_count' => 0, 'next_attempt_at' => $now, 'transport_snapshot' => json_encode($data->transport_snapshot, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now],
        ], ['module_id', 'order_id', 'external_trader_id'], ['updated_at']);
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
