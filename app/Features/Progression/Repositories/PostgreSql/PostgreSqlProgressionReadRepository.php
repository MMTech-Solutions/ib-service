<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql;

use App\Features\Progression\Contracts\Repositories\ProgressionReadRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionDistributionReadData;
use App\Features\Progression\DTOs\ProgressionReadPageData;
use App\Features\Progression\DTOs\ProgressionReadQueryData;
use App\Features\Progression\DTOs\ProgressionResultReadData;
use App\Features\Progression\DTOs\ProgressionRunReadData;
use App\Features\Progression\Exceptions\ProgressionArtifactNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlProgressionReadRepository implements ProgressionReadRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function read(ProgressionReadQueryData $query): ProgressionReadPageData
    {
        $table = match ($query->resource) {
            'distributions' => 'progression_activity_distributions', 'runs' => 'progression_runs', 'results' => 'progression_run_results', default => throw new \LogicException('Invalid projection.')
        };
        if ($query->runId !== null && ! $this->connection->table('progression_runs')->where('id', $query->runId)->exists()) {
            throw ProgressionArtifactNotFoundException::missing();
        }
        $builder = $this->connection->table($table.' as item');
        if ($query->id !== null) {
            $builder->where('item.id', $query->id);
        }
        if ($query->resource === 'results') {
            $builder->where('item.run_id', $query->runId);
        }
        foreach ($query->filters as $key => $value) {
            if (in_array($key, ['plan_id', 'subscription_id'], true) && $query->resource === 'distributions') {
                $builder->whereExists(function ($sub) use ($key, $value): void {
                    $sub->selectRaw('1')->from('progression_activity_evaluations as e')->whereColumn('e.activity_distribution_id', 'item.id')->where('e.'.$key, $value);
                });
            } elseif (in_array($key, ['window_from', 'window_to'], true)) {
                $builder->where('item.window_starts_at', $key === 'window_from' ? '>=' : '<', CarbonImmutable::parse($value)->utc());
            } elseif (in_array($key, ['resolved_at_from', 'resolved_at_to'], true)) {
                $builder->where('item.resolved_at', $key === 'resolved_at_from' ? '>=' : '<', CarbonImmutable::parse($value)->utc());
            } else {
                $builder->where('item.'.$key, $value);
            }
        }
        $total = (clone $builder)->count();
        $rows = $builder->orderBy('item.created_at')->orderBy('item.id')->offset($query->id === null ? ($query->page - 1) * $query->perPage : 0)->limit($query->id === null ? $query->perPage : 1)->get();
        $items = $rows->map(fn (object $row) => match ($query->resource) {
            'distributions' => $this->distribution($row), 'runs' => $this->run($row), 'results' => $this->result($row),
        })->all();

        return new ProgressionReadPageData($items, $total);
    }

    private function distribution(object $row): ProgressionDistributionReadData
    {
        $beneficiaries = $this->connection->table('progression_activity_distribution_beneficiaries')->where('distribution_id', $row->id)
            ->orderBy('distribution_level')->orderBy('beneficiary_external_user_id')->get()->map(static fn ($item): array => [
                'beneficiary_external_user_id' => $item->beneficiary_external_user_id, 'distribution_level' => (int) $item->distribution_level,
            ])->all();

        return new ProgressionDistributionReadData($row->id, $row->module_id, $row->source_activity_id, $row->source_external_user_id, $this->iso($row->resolved_at), $beneficiaries);
    }

    private function run(object $row): ProgressionRunReadData
    {
        return new ProgressionRunReadData($row->id, $row->plan_id, $this->iso($row->window_starts_at), $this->iso($row->window_ends_at), $row->status, $this->iso($row->started_at), $this->iso($row->completed_at), (new PostgreSqlProgressionRunRepository($this->connection))->snapshot($row->id));
    }

    private function result(object $row): ProgressionResultReadData
    {
        $application = $this->connection->table('progression_placement_applications')->where('run_result_id', $row->id)->first();
        $snapshot = (new PostgreSqlProgressionRunRepository($this->connection))->snapshot($row->run_id);
        $participant = $snapshot === null ? null : collect($snapshot->participants)->firstWhere('subscription_id', $row->subscription_id);

        return new ProgressionResultReadData($row->id, $row->run_id, $row->subscription_id, $row->status,
            $row->total_points === null ? null : (string) $row->total_points, $row->target_program_id, (int) $row->attempt_count,
            $row->status === 'failed' ? ($row->attempt_count > 0 ? 'retryable_failure' : 'pending_evaluation') : null,
            $this->iso($row->decision_at), $this->iso($row->completed_at), $participant['is_evaluable'] ?? null,
            $row->status === 'skipped' ? 'subscription_not_evaluable' : null,
            ['status' => $application !== null ? 'completed' : ($row->placement_failure_code !== null ? 'failed' : ($row->status === 'completed' ? 'pending' : 'not_ready')),
                'outcome' => $application?->outcome, 'failure_code' => $row->placement_failure_code, 'attempt_count' => (int) $row->placement_attempt_count,
                'last_attempt_at' => $this->iso($row->placement_last_attempt_at), 'applied_at' => $this->iso($application?->applied_at)],
            $row->original_decision === null ? null : json_decode($row->original_decision, true, flags: JSON_THROW_ON_ERROR),
            json_decode($row->recovery_attempts ?? '[]', true, flags: JSON_THROW_ON_ERROR));
    }

    private function iso(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toISOString();
    }
}
