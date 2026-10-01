<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use App\Features\Rules\Contracts\Data\V1\ResolveCpaRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveCpaRuleContextPort;
use Illuminate\Database\ConnectionInterface;

final class ResolveCpaRuleContextUseCase implements ResolveCpaRuleContextPort
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(ResolveCpaRuleContextQueryData $query): CpaRuleContextData
    {
        $row = $this->connection->table('program_cpa_rule_assignments as cpa')
            ->join('rule_assignments as assignments', function ($join): void {
                $join->on('assignments.program_id', '=', 'cpa.program_id')
                    ->on('assignments.rule_id', '=', 'cpa.rule_id')
                    ->on('assignments.rule_version_id', '=', 'cpa.rule_version_id');
            })->join('rules', 'rules.id', '=', 'cpa.rule_id')
            ->join('rule_versions', 'rule_versions.id', '=', 'cpa.rule_version_id')
            ->where('cpa.program_id', $query->program_id)
            ->where('cpa.starts_at', '<=', $query->occurred_at)
            ->where(fn ($builder) => $builder->whereNull('cpa.ends_at')->orWhere('cpa.ends_at', '>', $query->occurred_at))
            ->where('assignments.starts_at', '<=', $query->occurred_at)
            ->where(fn ($builder) => $builder->whereNull('assignments.ends_at')->orWhere('assignments.ends_at', '>', $query->occurred_at))
            ->where('rules.strategy_type', 'cpa_fixed_amount')
            ->where('rule_versions.status', 'published')
            ->select(['assignments.id as rule_assignment_id', 'cpa.rule_id', 'cpa.rule_version_id', 'assignments.module_id', 'rule_versions.configuration'])
            ->first();

        if ($row === null) {
            return CpaRuleContextData::absent();
        }

        $configuration = is_string($row->configuration) ? json_decode($row->configuration, true) : $row->configuration;

        return new CpaRuleContextData(
            (string) $row->rule_assignment_id,
            (string) $row->rule_id,
            (string) $row->rule_version_id,
            (string) $row->module_id,
            is_array($configuration) ? $configuration : null,
        );
    }
}
