<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Repositories\PostgreSql;

use App\Features\Rules\Assignments\Contracts\Repositories\CpaConfigurationRepositoryInterface;
use App\Features\Rules\Assignments\DTOs\CpaConfigurationData;
use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlCpaConfigurationRepository implements CpaConfigurationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(string $programId, string $at): CpaRuleContextData
    {
        $row = $this->connection->table('program_cpa_rule_assignments as cpa')->join('rule_versions as v', 'v.id', '=', 'cpa.rule_version_id')
            ->where('cpa.program_id', $programId)->where('cpa.starts_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('cpa.ends_at')->orWhere('cpa.ends_at', '>', $at))
            ->first(['cpa.*', 'v.configuration']);

        return $row === null ? CpaRuleContextData::absent() : new CpaRuleContextData($row->id, $row->rule_id, $row->rule_version_id, json_decode($row->configuration, true, 512, JSON_THROW_ON_ERROR));
    }

    public function publishedVersion(string $planId, string $versionId): ?array
    {
        $row = $this->connection->table('rule_versions as v')->join('rules as r', 'r.id', '=', 'v.rule_id')
            ->where('v.id', $versionId)->where('r.plan_id', $planId)->where('r.strategy_type', 'cpa_fixed_amount')->where('v.status', 'published')->first(['r.id', 'v.configuration']);

        return $row === null ? null : ['rule_id' => $row->id, 'configuration' => json_decode($row->configuration, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function current(string $programId): ?CpaConfigurationData
    {
        $row = $this->connection->table('program_cpa_rule_assignments')->where('program_id', $programId)->whereNull('ends_at')->first();

        return $row === null ? null : $this->present($row);
    }

    public function replace(string $programId, string $ruleId, string $versionId, string $actorId, string $at): CpaConfigurationData
    {
        return $this->connection->transaction(function () use ($programId, $ruleId, $versionId, $actorId, $at): CpaConfigurationData {
            $this->lock($programId);
            $current = $this->current($programId);
            if ($current?->rule_version_id === $versionId) {
                return $current;
            }
            $this->close($programId, $actorId, $at);
            $id = (string) Str::uuid7();
            $this->connection->table('program_cpa_rule_assignments')->insert(['id' => $id, 'program_id' => $programId, 'rule_id' => $ruleId, 'rule_version_id' => $versionId, 'actor_id' => $actorId, 'starts_at' => $at, 'created_at' => $at, 'updated_at' => $at]);

            return $this->current($programId);
        });
    }

    public function withdraw(string $programId, string $actorId, string $at): void
    {
        $this->connection->transaction(function () use ($programId, $actorId, $at): void {
            $this->lock($programId);
            $this->close($programId, $actorId, $at);
        });
    }

    private function lock(string $programId): void
    {
        $this->connection->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['cpa:'.$programId]);
    }

    private function close(string $programId, string $actorId, string $at): void
    {
        $this->connection->table('program_cpa_rule_assignments')->where('program_id', $programId)->whereNull('ends_at')->update(['ends_at' => $at, 'withdrawn_by' => $actorId, 'updated_at' => $at]);
    }

    private function present(object $row): CpaConfigurationData
    {
        return new CpaConfigurationData($row->id, $row->program_id, $row->rule_id, $row->rule_version_id, CarbonImmutable::parse($row->starts_at)->toIso8601String(), $row->ends_at === null ? null : CarbonImmutable::parse($row->ends_at)->toIso8601String());
    }
}
