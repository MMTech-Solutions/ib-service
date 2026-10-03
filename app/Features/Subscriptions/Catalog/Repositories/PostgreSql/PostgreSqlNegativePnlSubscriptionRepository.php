<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Repositories\PostgreSql;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\NegativePnlSubscriptionRepositoryInterface;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionSegmentData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlNegativePnlSubscriptionRepository implements NegativePnlSubscriptionRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function segments(string $subscriptionId, string $from, string $until): array
    {
        return $this->connection->table('subscription_placements')->where('subscription_id', $subscriptionId)->where('effective_from', '<=', $until)->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $from))->orderBy('effective_from')->get()->map(static fn ($row): NegativePnlSubscriptionSegmentData => new NegativePnlSubscriptionSegmentData($row->program_id, CarbonImmutable::parse($row->effective_from)->utc()->toISOString(), $row->effective_until === null ? null : CarbonImmutable::parse($row->effective_until)->utc()->toISOString()))->all();
    }

    public function list(string $programId, string $startsAt, ?string $endsAt, ?string $afterId, int $limit): array
    {
        $query = $this->connection->table('subscriptions')->whereNotNull('activated_at')
            ->whereExists(function ($q) use ($programId, $startsAt, $endsAt): void {
                $q->selectRaw('1')->from('subscription_placements')->whereColumn('subscription_placements.subscription_id', 'subscriptions.id')->where('program_id', $programId)->where(fn ($w) => $w->whereNull('effective_until')->orWhere('effective_until', '>', $startsAt));
                if ($endsAt !== null) {
                    $q->where('effective_from', '<', $endsAt);
                }
            });
        if ($afterId !== null) {
            $query->where('subscriptions.id', '>', $afterId);
        }

        return $query->orderBy('subscriptions.id')->limit($limit)->get()->map(function ($row) use ($programId, $startsAt, $endsAt): NegativePnlSubscriptionData {
            $incoming = $this->connection->table('subscriptions')->where('replaces_subscription_id', $row->id)->value('id');
            $operation = $incoming === null ? null : $this->connection->table('subscription_changes')->where('subscription_id', $incoming)->orderBy('occurred_at')->value('operation_id');
            $placements = $this->connection->table('subscription_placements')->where('subscription_id', $row->id)->where('program_id', $programId)->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $startsAt));
            if ($endsAt !== null) {
                $placements->where('effective_from', '<', $endsAt);
            }
            $start = $placements->min('effective_from');

            return new NegativePnlSubscriptionData($row->id, $row->external_user_id, $row->plan_id, CarbonImmutable::parse($row->activated_at)->utc()->toISOString(), $row->closed_at === null ? null : CarbonImmutable::parse($row->closed_at)->utc()->toISOString(), $incoming, $operation, $start === null ? null : CarbonImmutable::parse($start)->utc()->toISOString());
        })->all();
    }
}
