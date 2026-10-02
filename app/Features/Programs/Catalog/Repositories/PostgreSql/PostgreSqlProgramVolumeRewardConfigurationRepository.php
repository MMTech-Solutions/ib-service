<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramVolumeRewardConfigurationRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlProgramVolumeRewardConfigurationRepository implements ProgramVolumeRewardConfigurationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function replace(string $programId, string $mode, string $at): array
    {
        return $this->connection->transaction(function () use ($programId, $mode, $at): array {
            $current = $this->connection->table('program_volume_reward_configurations')->where('program_id', $programId)->whereNull('ends_at')->lockForUpdate()->first();
            if ($current !== null && $current->mode === $mode) {
                return (array) $current;
            }
            if ($current !== null) {
                $this->connection->table('program_volume_reward_configurations')->where('id', $current->id)->update(['ends_at' => $at, 'updated_at' => $at]);
            }
            $record = ['id' => (string) Str::uuid7(), 'program_id' => $programId, 'mode' => $mode, 'starts_at' => $at, 'ends_at' => null, 'created_at' => $at, 'updated_at' => $at];
            $this->connection->table('program_volume_reward_configurations')->insert($record);

            return $record;
        });
    }
}
