<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Rewards\Contracts\Repositories\CpaVerificationProgressRepositoryInterface;
use App\Features\Rewards\DTOs\CpaVerificationProgressData;
use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\DTOs\CpaVerificationProgressPageData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final class PostgreSqlCpaVerificationProgressRepository implements CpaVerificationProgressRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function paginate(CpaVerificationProgressListQueryData $query): CpaVerificationProgressPageData
    {
        $paginator = $this->connection->table('cpa_contexts as contexts')
            ->join('cpa_verification_progress as progress', 'progress.cpa_context_id', '=', 'contexts.id')
            ->when($query->ib_user_id !== null, fn ($builder) => $builder->where('contexts.ib_user_id', $query->ib_user_id))
            ->when($query->referred_user_id !== null, fn ($builder) => $builder->where('contexts.referred_user_id', $query->referred_user_id))
            ->when($query->program_id !== null, fn ($builder) => $builder->where('contexts.program_id', $query->program_id))
            ->when($query->module_id !== null, fn ($builder) => $builder->where('contexts.module_id', $query->module_id))
            ->when($query->status !== null, fn ($builder) => $builder->where('progress.status', $query->status))
            ->orderByDesc('contexts.captured_at')
            ->orderByDesc('contexts.id')
            ->paginate($query->per_page, [
                'contexts.id as context_id', 'contexts.referred_user_id', 'contexts.ib_user_id', 'contexts.plan_id',
                'contexts.program_id', 'contexts.module_id', 'contexts.rule_assignment_id', 'contexts.rule_id',
                'contexts.rule_version_id', 'contexts.reward_id', 'contexts.requirements_snapshot', 'progress.status',
                'progress.observed_volume', 'progress.required_volume', 'progress.volume_unit_code',
                'progress.observed_deposit_minor', 'progress.required_deposit_minor', 'progress.currency_code',
                'progress.volume_satisfied', 'progress.deposit_satisfied', 'progress.observed_from',
                'progress.observed_until', 'progress.last_evaluated_at', 'progress.last_error_code',
            ], 'page', $query->page);

        return new CpaVerificationProgressPageData(
            items: collect($paginator->items())->map(fn (stdClass $row): CpaVerificationProgressData => $this->toData($row))->all(),
            current_page: $paginator->currentPage(),
            per_page: $paginator->perPage(),
            total: $paginator->total(),
            last_page: $paginator->lastPage(),
        );
    }

    private function toData(stdClass $row): CpaVerificationProgressData
    {
        $requirements = json_decode((string) $row->requirements_snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new CpaVerificationProgressData(
            id: (string) $row->context_id,
            referred_user_id: (string) $row->referred_user_id,
            status: (string) $row->status,
            observed_volume: (string) $row->observed_volume,
            required_volume: (string) $row->required_volume,
            volume_unit_code: (string) $row->volume_unit_code,
            observed_deposit_minor: (int) $row->observed_deposit_minor,
            required_deposit_minor: (int) $row->required_deposit_minor,
            currency_code: (string) $row->currency_code,
            currency_precision: (int) $requirements['currency_precision'],
            volume_satisfied: (bool) $row->volume_satisfied,
            deposit_satisfied: (bool) $row->deposit_satisfied,
            observed_from: $this->timestamp($row->observed_from),
            observed_until: $this->nullableTimestamp($row->observed_until),
            last_evaluated_at: $this->nullableTimestamp($row->last_evaluated_at),
            ib_user_id: (string) $row->ib_user_id,
            plan_id: (string) $row->plan_id,
            program_id: (string) $row->program_id,
            module_id: $row->module_id === null ? null : (string) $row->module_id,
            rule_assignment_id: $row->rule_assignment_id === null ? null : (string) $row->rule_assignment_id,
            rule_id: (string) $row->rule_id,
            rule_version_id: (string) $row->rule_version_id,
            reward_id: $row->reward_id === null ? null : (string) $row->reward_id,
            last_error_code: $row->last_error_code === null ? null : (string) $row->last_error_code,
        );
    }

    private function timestamp(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value)->utc()->toIso8601String();
    }

    private function nullableTimestamp(mixed $value): ?string
    {
        return $value === null ? null : $this->timestamp($value);
    }
}
