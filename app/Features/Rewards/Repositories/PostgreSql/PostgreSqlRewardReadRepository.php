<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Rewards\DTOs\NegativePnlJobReadData;
use App\Features\Rewards\DTOs\NegativePnlPeriodReadData;
use App\Features\Rewards\DTOs\RewardReadData;
use App\Features\Rewards\DTOs\RewardReadPageData;
use App\Features\Rewards\DTOs\RewardReadQueryData;
use App\Features\Rewards\Repositories\RewardReadRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

final class PostgreSqlRewardReadRepository implements RewardReadRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function paginate(RewardReadQueryData $query): RewardReadPageData
    {
        $builder = $this->query($query);
        $total = $builder->count();
        $alias = $query->resource === 'rewards' ? 'r' : ($query->resource === 'jobs' ? 'j' : 'p');
        $rows = $builder->orderBy($alias.'.id', 'desc')->forPage($query->page, $query->per_page)->get([$alias.'.*']);

        return new RewardReadPageData($rows->map(fn ($row) => $this->map($row, $query))->all(), $total, $query->page, $query->per_page);
    }

    public function find(RewardReadQueryData $query): RewardReadData|NegativePnlJobReadData|NegativePnlPeriodReadData|null
    {
        $alias = $query->resource === 'rewards' ? 'r' : ($query->resource === 'jobs' ? 'j' : 'p');
        $row = $this->query($query)->where($alias.'.id', $query->id)->first([$alias.'.*']);

        return $row === null ? null : $this->map($row, $query);
    }

    private function query(RewardReadQueryData $query): Builder
    {
        $builder = match ($query->resource) {
            'rewards' => $this->connection->table('rewards as r'),
            'jobs' => $this->connection->table('negative_pnl_jobs as j'),
            'periods' => $this->connection->table('negative_pnl_periods as p')->join('negative_pnl_jobs as j', 'j.id', '=', 'p.job_id'),
            default => throw new InvalidArgumentException('Unsupported reward read resource'),
        };
        if ($query->resource !== 'rewards' && (! $query->include_audit || $query->beneficiary_id !== null)) {
            $builder->whereRaw('1 = 0');
        }
        $alias = $query->resource === 'rewards' ? 'r' : 'j';
        if ($query->beneficiary_id !== null) {
            $builder->where('r.beneficiary_user_id', $query->beneficiary_id);
        }
        foreach ($query->filters as $field => $value) {
            $column = match ($field) {
                'beneficiary_id' => $alias.'.'.($query->resource === 'rewards' ? 'beneficiary_user_id' : 'beneficiary_id'),
                'plan_id', 'module_id', 'subscription_id', 'server_group_id' => $alias.'.'.$field,
                'commission_type' => 'r.commission_type', 'job_id' => 'p.job_id',
                default => null,
            };
            if ($column !== null) {
                $builder->where($column, $value);
            } elseif ($field === 'program_id') {
                if ($query->resource === 'rewards') {
                    $builder->where('r.program_id', $value);
                } elseif ($query->resource === 'periods') {
                    $builder->where('p.inputs->subscription->program_id', $value);
                } else {
                    $builder->whereExists(fn ($periods) => $periods->selectRaw('1')->from('negative_pnl_periods')->whereColumn('job_id', 'j.id')->where('inputs->subscription->program_id', $value));
                }
            } elseif ($field === 'status') {
                if ($query->resource === 'jobs') {
                    $this->jobStatus($builder, $value);
                } else {
                    $builder->where(($query->resource === 'rewards' ? 'r' : 'p').'.status', $value);
                }
            } elseif (in_array($field, ['occurred_from', 'occurred_until'], true)) {
                $dateColumn = match ($query->resource) {
                    'rewards' => 'r.created_at', 'jobs' => 'j.next_cut_at', default => 'p.occurred_until'
                };
                $builder->where($dateColumn, $field === 'occurred_from' ? '>=' : '<', CarbonImmutable::parse($value)->utc());
            }
        }

        return $builder;
    }

    private function jobStatus(Builder $builder, string $status): void
    {
        if ($status === 'completed') {
            $builder->whereNotNull('j.finished_at');

            return;
        }
        $builder->whereNull('j.finished_at');
        if ($status === 'processing') {
            $builder->where('j.lease_expires_at', '>', CarbonImmutable::now('UTC'));

            return;
        }
        $builder->where(fn ($lease) => $lease->whereNull('j.lease_expires_at')->orWhere('j.lease_expires_at', '<=', CarbonImmutable::now('UTC')));
        $status === 'failed' ? $builder->whereNotNull('j.error_code') : $builder->whereNull('j.error_code');
    }

    private function map(object $row, RewardReadQueryData $query): RewardReadData|NegativePnlJobReadData|NegativePnlPeriodReadData
    {
        if ($query->resource === 'rewards') {
            $audit = null;
            if ($query->include_audit) {
                $audit = ['beneficiary_id' => $row->beneficiary_user_id, 'network_level' => (int) $row->network_level, 'rule_assignment_id' => $row->rule_assignment_id, 'rule_id' => $row->rule_id, 'rule_version_id' => $row->rule_version_id, 'summary_snapshot' => json_decode($row->summary_snapshot, true, 512, JSON_THROW_ON_ERROR), 'settlement_provider' => $row->settlement_provider, 'settlement_reference_id' => $row->settlement_reference_id, 'settlement_attempt_count' => (int) $row->settlement_attempt_count, 'last_settlement_error_code' => $row->last_settlement_error_code, 'reconciliation_hold_code' => $row->reconciliation_hold_code, 'evidence' => $this->connection->table('reward_evidence')->where('reward_id', $row->id)->orderBy('id')->get()->map(fn ($item) => (array) $item)->all(), 'operations' => $this->connection->table('reward_financial_operations')->where('reward_id', $row->id)->orderBy('created_at')->get(['id', 'operation_type', 'status', 'outcome', 'compensation_reward_id', 'provider_reference_id', 'last_error_code'])->map(fn ($item) => (array) $item)->all()];
            }

            return new RewardReadData($row->id, $row->status, $row->commission_type, (int) $row->amount_minor, $row->currency_code, (int) $row->currency_precision, $row->plan_id, $row->program_id, $row->module_id, $this->timestamp($row->created_at), $this->timestamp($row->settled_at), $row->compensates_reward_id, $audit);
        }
        if ($query->resource === 'jobs') {
            $status = $row->finished_at !== null ? 'completed' : ($row->lease_expires_at !== null && CarbonImmutable::parse($row->lease_expires_at)->greaterThan(CarbonImmutable::now('UTC')) ? 'processing' : ($row->error_code !== null ? 'failed' : 'pending'));
            $closure = $this->connection->table('negative_pnl_pending_closures')->where('subscription_id', $row->subscription_id)->first(['incoming_subscription_id', 'operation_id', 'closed_at', 'discovered_at', 'completed_at']);

            return new NegativePnlJobReadData($row->id, $row->subscription_id, $row->beneficiary_id, $row->plan_id, $row->module_id, $row->server_group_id, $row->cadence, $status, $this->timestamp($row->next_cut_at), $this->timestamp($row->cursor_at), $this->timestamp($row->closed_at), $this->timestamp($row->finished_at), $row->error_code, $this->timestamp($row->retry_at), $closure === null ? null : (array) $closure);
        }
        $ids = $this->connection->table('rewards')->where('summary_snapshot->period_id', $row->id)->orderBy('id')->pluck('id')->all();

        return new NegativePnlPeriodReadData($row->id, $row->job_id, $row->status, $this->timestamp($row->occurred_until), json_decode($row->inputs, true, 512, JSON_THROW_ON_ERROR), json_decode($row->receipts, true, 512, JSON_THROW_ON_ERROR), json_decode($row->outcomes, true, 512, JSON_THROW_ON_ERROR), $ids);
    }

    private function timestamp(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toISOString();
    }
}
