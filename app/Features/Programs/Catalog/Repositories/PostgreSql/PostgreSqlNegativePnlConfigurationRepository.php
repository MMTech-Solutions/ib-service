<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql;

use App\Features\Programs\Catalog\Contracts\Repositories\NegativePnlConfigurationRepositoryInterface;
use App\Features\Programs\Contracts\Data\V1\NegativePnlGroupConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlPaymentLevelData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlNegativePnlConfigurationRepository implements NegativePnlConfigurationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transactionForProgram(string $programId, Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($programId, $callback): mixed {
            $this->connection->table('programs')->where('id', $programId)->lockForUpdate()->first();

            return $callback();
        }, 3);
    }

    public function replace(string $programId, string $cadence, string $actorId, string $at, array $groups): ?NegativePnlProgramConfigurationData
    {
        $current = $this->resolve(new ResolveNegativePnlProgramConfigurationQueryData($programId));
        if ($current !== null && $current->cadence === $cadence && $this->identity($current->groups) === $this->identity($groups)) {
            return $current;
        }
        if ($current !== null) {
            $this->connection->table('program_negative_pnl_configuration_revisions')->where('id', $current->id)->update(['ends_at' => $at, 'closed_by_actor_id' => $actorId]);
        }
        if ($groups === []) {
            return null;
        }
        $id = (string) Str::uuid7();
        $this->connection->table('program_negative_pnl_configuration_revisions')->insert(['id' => $id, 'program_id' => $programId, 'cadence' => $cadence, 'actor_id' => $actorId, 'starts_at' => $at]);
        foreach ($groups as $group) {
            $this->connection->table('program_negative_pnl_groups')->insert(['id' => (string) Str::uuid7(), 'configuration_id' => $id, 'module_id' => $group->module_id, 'server_group_id' => $group->server_group_id, 'rule_version_id' => $group->rule_version_id, 'economic_context' => json_encode($group->toArray(), JSON_THROW_ON_ERROR)]);
        }

        return $this->resolve(new ResolveNegativePnlProgramConfigurationQueryData($programId));
    }

    public function resolve(ResolveNegativePnlProgramConfigurationQueryData $query): ?NegativePnlProgramConfigurationData
    {
        $builder = $this->connection->table('program_negative_pnl_configuration_revisions')->where('program_id', $query->program_id);
        if ($query->occurred_at === null) {
            $builder->whereNull('ends_at');
        } else {
            $at = CarbonImmutable::parse($query->occurred_at)->utc()->toISOString();
            $builder->where('starts_at', '<=', $at)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
        }
        $row = $builder->first();
        if ($row === null) {
            return null;
        }
        $groups = $this->connection->table('program_negative_pnl_groups')->where('configuration_id', $row->id)->orderBy('module_id')->orderBy('server_group_id');
        if ($query->module_id !== null) {
            $groups->where('module_id', $query->module_id);
        }
        if ($query->server_group_id !== null) {
            $groups->where('server_group_id', $query->server_group_id);
        }
        $items = $groups->get()->map(static function ($group): NegativePnlGroupConfigurationData {
            $data = json_decode($group->economic_context, true, 512, JSON_THROW_ON_ERROR);
            $data['levels'] = array_map(static fn (array $level): NegativePnlPaymentLevelData => new NegativePnlPaymentLevelData($level['distribution_level'], $level['rate']), $data['levels']);

            return new NegativePnlGroupConfigurationData(...$data);
        })->all();
        if ($items === []) {
            return null;
        }

        return new NegativePnlProgramConfigurationData((string) $row->id, (string) $row->program_id, (string) $row->cadence, (string) $row->actor_id, CarbonImmutable::parse($row->starts_at)->utc()->toISOString(), $row->ends_at === null ? null : CarbonImmutable::parse($row->ends_at)->utc()->toISOString(), $row->closed_by_actor_id, $items);
    }

    /** @param list<NegativePnlGroupConfigurationData> $groups @return list<string> */
    private function identity(array $groups): array
    {
        $keys = array_map(static fn (NegativePnlGroupConfigurationData $group): string => json_encode([$group->module_id, $group->server_group_id, $group->rule_version_id], JSON_THROW_ON_ERROR), $groups);
        sort($keys);

        return $keys;
    }
}
