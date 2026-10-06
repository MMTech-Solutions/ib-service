<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Rewards\DTOs\CpaSourceProgressData;
use App\Features\Rewards\DTOs\CpaVerificationProgressData;
use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\DTOs\CpaVerificationProgressPageData;
use App\Features\Rewards\Repositories\CpaVerificationProgressRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlCpaVerificationProgressRepository implements CpaVerificationProgressRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function paginate(CpaVerificationProgressListQueryData $query): CpaVerificationProgressPageData
    {
        $paginator = $this->connection->table('cpa_contexts as contexts')
            ->join('cpa_verification_progress as progress', 'progress.cpa_context_id', '=', 'contexts.id')
            ->leftJoin('rewards', 'rewards.id', '=', 'contexts.reward_id')
            ->when($query->ib_user_id !== null, fn ($q) => $q->where('contexts.ib_user_id', $query->ib_user_id))
            ->when($query->referred_user_id !== null, fn ($q) => $q->where('contexts.referred_user_id', $query->referred_user_id))
            ->when($query->program_id !== null, fn ($q) => $q->where('contexts.program_id', $query->program_id))
            ->when($query->module_id !== null, fn ($q) => $q->whereExists(fn ($s) => $s->selectRaw('1')->from('cpa_sources')->whereColumn('cpa_sources.cpa_context_id', 'contexts.id')->where('cpa_sources.module_id', $query->module_id)))
            ->when($query->status !== null, fn ($q) => $q->where('progress.status', $query->status))
            ->orderByDesc('contexts.captured_at')->orderByDesc('contexts.id')
            ->paginate($query->per_page, ['contexts.*', 'progress.status', 'progress.observed_volume_points', 'progress.observed_deposit_points', 'progress.observed_deposit_minor', 'progress.volume_satisfied', 'progress.deposit_satisfied', 'progress.observed_from', 'progress.last_evaluated_at', 'progress.last_error_code', 'progress.expiration_reason', 'rewards.status as reward_financial_status', 'rewards.reconciliation_hold_code as reward_reconciliation_hold_code'], 'page', $query->page);
        $ids = array_map(static fn (object $row): string => $row->id, $paginator->items());
        $sources = $this->connection->table('cpa_sources')->whereIn('cpa_context_id', $ids)->orderBy('source_key')->get();
        $totals = $this->connection->table('cpa_contributions')->whereIn('cpa_context_id', $ids)->groupBy('cpa_source_id')->selectRaw('cpa_source_id, SUM(quantity) as quantity, SUM(points) as points')->get()->keyBy('cpa_source_id');
        $items = [];
        foreach ($paginator->items() as $row) {
            $configuration = json_decode($row->requirements_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $sourceData = [];
            foreach ($sources->where('cpa_context_id', $row->id) as $source) {
                $total = $totals->get($source->id);
                $sourceData[] = new CpaSourceProgressData($source->kind, $source->module_id, $source->status, (string) ($total->quantity ?? '0'), (string) ($total->points ?? '0'), $this->timestamp($source->observed_until), $this->timestamp($source->last_evaluated_at), $source->last_error_code);
            }
            $items[] = new CpaVerificationProgressData(
                $row->id, $row->referred_user_id, $row->status, $row->observed_volume_points, $configuration['required_volume_points'],
                $row->observed_deposit_points, $configuration['required_deposit_points'], (int) $row->observed_deposit_minor, $configuration['deposit_currency'], $configuration['deposit_currency_precision'],
                (bool) $row->volume_satisfied, (bool) $row->deposit_satisfied, $this->timestamp($row->observed_from), $this->timestamp($row->last_evaluated_at),
                $sourceData, $row->ib_user_id, $row->plan_id, $row->program_id, $row->cpa_assignment_id, $row->rule_id, $row->rule_version_id,
                $row->reward_id, $row->last_error_code, $row->expiration_reason, $row->reward_financial_status, $row->reward_reconciliation_hold_code,
            );
        }

        return new CpaVerificationProgressPageData($items, $paginator->currentPage(), $paginator->perPage(), $paginator->total(), $paginator->lastPage());
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->utc()->toIso8601String();
    }
}
