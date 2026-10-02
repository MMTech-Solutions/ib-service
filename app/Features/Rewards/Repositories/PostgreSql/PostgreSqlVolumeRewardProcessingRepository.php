<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlVolumeRewardProcessingRepository
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function recordEvent(RecordVolumeRewardEventData $data): void
    {
        $now = now('UTC')->toIso8601String();
        $this->connection->table('volume_reward_event_receipts')->upsert([
            ['id' => (string) Str::uuid7(), 'module_id' => $data->module_id, 'order_id' => $data->order_id, 'external_trader_id' => $data->external_trader_id, 'status' => 'pending', 'attempt_count' => 0, 'next_attempt_at' => $now, 'transport_snapshot' => json_encode($data->transport_snapshot, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now],
        ], ['module_id', 'order_id', 'external_trader_id'], ['updated_at']);
    }
}
