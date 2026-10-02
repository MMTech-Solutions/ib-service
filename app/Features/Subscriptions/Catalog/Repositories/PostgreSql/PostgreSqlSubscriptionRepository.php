<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Repositories\PostgreSql;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionAggregatePageData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionListQueryData;
use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionOrigin;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateOpenSubscriptionException;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateSubscriptionReplacementException;
use App\Features\Subscriptions\Catalog\Exceptions\ProgramNotOnSubscriptionPlanException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionConcurrencyException;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\Models\SubscriptionChangeRecord;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\Models\SubscriptionPlacementRecord;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\Models\SubscriptionRecord;
use App\Features\Subscriptions\Contracts\Data\V1\ProgressionWindowSubscriptionData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

final class PostgreSqlSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?Subscription
    {
        $record = SubscriptionRecord::query()->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findOpenByExternalUserId(string $externalUserId): ?Subscription
    {
        $record = SubscriptionRecord::query()
            ->where('external_user_id', $externalUserId)
            ->whereIn('status', [
                SubscriptionStatus::Pending->value,
                SubscriptionStatus::Active->value,
            ])
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function hasOpenForPlan(string $planId): bool
    {
        return SubscriptionRecord::query()
            ->where('plan_id', $planId)
            ->whereIn('status', [
                SubscriptionStatus::Pending->value,
                SubscriptionStatus::Active->value,
            ])
            ->exists();
    }

    public function resolvePlacementAt(string $subscriptionId, string $occurredAt): ?SubscriptionPlacement
    {
        $subscription = $this->findById($subscriptionId);
        if ($subscription === null) {
            return null;
        }

        return $subscription->placementAt($occurredAt);
    }

    public function listPlacementContextsAt(string $externalUserId, string $occurredAt): array
    {
        $records = SubscriptionRecord::query()
            ->where('external_user_id', $externalUserId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $matches = [];

        foreach ($records as $record) {
            $subscription = $this->hydrate($record);
            $placement = $subscription->placementAt($occurredAt);
            if ($placement === null) {
                continue;
            }

            $matches[] = [
                'subscription' => $subscription,
                'placement' => $placement,
            ];
        }

        usort(
            $matches,
            static function (array $left, array $right): int {
                $fromCompare = strcmp(
                    $left['placement']->effectiveFrom,
                    $right['placement']->effectiveFrom,
                );
                if ($fromCompare !== 0) {
                    return $fromCompare;
                }

                return strcmp($left['placement']->id, $right['placement']->id);
            },
        );

        return $matches;
    }

    public function earliestActivatedAt(): ?string
    {
        $activatedAt = SubscriptionRecord::query()
            ->whereNotNull('activated_at')
            ->min('activated_at');

        return $activatedAt === null
            ? null
            : CarbonImmutable::parse((string) $activatedAt)->utc()->toISOString();
    }

    public function listForProgressionWindow(string $planId, string $windowStartsAt, string $windowEndsAt): array
    {
        return SubscriptionRecord::query()
            ->where('plan_id', $planId)
            ->whereNotNull('activated_at')
            ->where('activated_at', '<', $windowEndsAt)
            ->where(function ($query) use ($windowStartsAt): void {
                $query->whereNull('closed_at')->orWhere('closed_at', '>', $windowStartsAt);
            })
            ->orderBy('activated_at')
            ->orderBy('id')
            ->get()
            ->map(function (SubscriptionRecord $record) use ($windowStartsAt, $windowEndsAt): ProgressionWindowSubscriptionData {
                $isEvaluable = SubscriptionPlacementRecord::query()
                    ->where('subscription_id', $record->id)
                    ->where('is_fixed', false)
                    ->where('effective_from', '<', $windowEndsAt)
                    ->where(function ($query) use ($windowStartsAt): void {
                        $query->whereNull('effective_until')->orWhere('effective_until', '>', $windowStartsAt);
                    })
                    ->exists();

                return new ProgressionWindowSubscriptionData(
                    subscription_id: (string) $record->id,
                    is_evaluable: $isEvaluable,
                );
            })
            ->all();
    }

    public function paginate(SubscriptionListQueryData $query): SubscriptionAggregatePageData
    {
        $builder = SubscriptionRecord::query()
            ->when($query->planId !== null, fn ($builder) => $builder->where('plan_id', $query->planId))
            ->when($query->status !== null, fn ($builder) => $builder->where('status', $query->status->value))
            ->when(
                $query->externalUserId !== null,
                fn ($builder) => $builder->where('external_user_id', $query->externalUserId),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $builder->paginate($query->perPage, ['*'], 'page', $query->page);
        $subscriptions = collect($paginator->items())
            ->map(fn (SubscriptionRecord $record): Subscription => $this->hydrate($record))
            ->all();

        return new SubscriptionAggregatePageData(
            subscriptions: $subscriptions,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function create(Subscription $subscription): void
    {
        $this->transaction(function () use ($subscription): void {
            $this->assertProgramsBelongToPlan($subscription);

            try {
                SubscriptionRecord::query()->create($this->subscriptionAttributes($subscription));
            } catch (UniqueConstraintViolationException $exception) {
                throw $this->mapUniqueViolation($exception, $subscription);
            }

            foreach ($subscription->placements as $placement) {
                SubscriptionPlacementRecord::query()->create($this->placementAttributes($placement));
            }

            foreach ($subscription->changes as $change) {
                SubscriptionChangeRecord::query()->create($this->changeAttributes($change));
            }
        });
    }

    public function save(Subscription $subscription, int $expectedLockVersion): void
    {
        $this->transaction(function () use ($subscription, $expectedLockVersion): void {
            $this->assertProgramsBelongToPlan($subscription);
            $this->lockSubscription($subscription->id);

            $nextLockVersion = $expectedLockVersion + 1;
            $affected = SubscriptionRecord::query()
                ->whereKey($subscription->id)
                ->where('lock_version', $expectedLockVersion)
                ->update([
                    'status' => $subscription->status->value,
                    'activated_at' => $subscription->activatedAt,
                    'closed_at' => $subscription->closedAt,
                    'personal_rate' => $subscription->personalRate,
                    'is_master' => $subscription->isMaster,
                    'master_rate' => $subscription->masterRate,
                    'lock_version' => $nextLockVersion,
                    'updated_at' => $subscription->updatedAt,
                ]);

            if ($affected !== 1) {
                throw SubscriptionConcurrencyException::forSubscription($subscription->id);
            }

            $this->syncPlacements($subscription);
            $this->syncChanges($subscription);
            $subscription->lockVersion = $nextLockVersion;
        });
    }

    public function replace(
        Subscription $outgoing,
        int $expectedOutgoingLockVersion,
        Subscription $incoming,
    ): void {
        $this->transaction(function () use ($outgoing, $expectedOutgoingLockVersion, $incoming): void {
            $this->save($outgoing, $expectedOutgoingLockVersion);
            $this->create($incoming);
        });
    }

    private function syncPlacements(Subscription $subscription): void
    {
        /** @var Collection<int, SubscriptionPlacementRecord> $existing */
        $existing = SubscriptionPlacementRecord::query()
            ->where('subscription_id', $subscription->id)
            ->get()
            ->keyBy(static fn (SubscriptionPlacementRecord $record): string => (string) $record->id);

        foreach ($subscription->placements as $placement) {
            $attributes = $this->placementAttributes($placement);
            $record = $existing->get($placement->id);

            if ($record === null) {
                try {
                    SubscriptionPlacementRecord::query()->create($attributes);
                } catch (UniqueConstraintViolationException) {
                    throw SubscriptionConcurrencyException::forSubscription($subscription->id);
                }

                continue;
            }

            $record->fill([
                'effective_until' => $placement->effectiveUntil,
                'updated_at' => $placement->updatedAt,
            ])->save();
        }
    }

    private function syncChanges(Subscription $subscription): void
    {
        $existingIds = SubscriptionChangeRecord::query()
            ->where('subscription_id', $subscription->id)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($subscription->changes as $change) {
            if (in_array($change->id, $existingIds, true)) {
                continue;
            }

            SubscriptionChangeRecord::query()->create($this->changeAttributes($change));
        }
    }

    private function lockSubscription(string $subscriptionId): void
    {
        $locked = $this->connection->table('subscriptions')
            ->where('id', $subscriptionId)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw SubscriptionConcurrencyException::forSubscription($subscriptionId);
        }
    }

    private function assertProgramsBelongToPlan(Subscription $subscription): void
    {
        $programIds = [];

        foreach ($subscription->placements as $placement) {
            $programIds[] = $placement->programId;
        }

        foreach ($subscription->changes as $change) {
            if ($change->previousProgramId !== null) {
                $programIds[] = $change->previousProgramId;
            }

            if ($change->nextProgramId !== null) {
                $programIds[] = $change->nextProgramId;
            }
        }

        $programIds = array_values(array_unique($programIds));
        if ($programIds === []) {
            return;
        }

        $matching = $this->connection->table('programs')
            ->where('plan_id', $subscription->planId)
            ->whereIn('id', $programIds)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($programIds as $programId) {
            if (! in_array($programId, $matching, true)) {
                throw ProgramNotOnSubscriptionPlanException::forProgram($programId, $subscription->planId);
            }
        }
    }

    private function mapUniqueViolation(
        UniqueConstraintViolationException $exception,
        Subscription $subscription,
    ): UniqueConstraintViolationException|DuplicateOpenSubscriptionException|DuplicateSubscriptionReplacementException {
        $message = $exception->getMessage();

        if (str_contains($message, 'subscriptions_open_user_unique')) {
            return DuplicateOpenSubscriptionException::forUser($subscription->externalUserId);
        }

        if (str_contains($message, 'subscriptions_replaces_unique')) {
            return DuplicateSubscriptionReplacementException::forSubscription(
                (string) $subscription->replacesSubscriptionId,
            );
        }

        return $exception;
    }

    private function hydrate(SubscriptionRecord $record): Subscription
    {
        $placements = SubscriptionPlacementRecord::query()
            ->where('subscription_id', $record->id)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionPlacementRecord $placement): SubscriptionPlacement => new SubscriptionPlacement(
                id: (string) $placement->id,
                subscriptionId: (string) $placement->subscription_id,
                programId: (string) $placement->program_id,
                condition: PlacementCondition::fromBoolean((bool) $placement->is_fixed),
                effectiveFrom: $placement->effective_from->utc()->toISOString(),
                effectiveUntil: $placement->effective_until?->utc()->toISOString(),
                createdAt: $placement->created_at->utc()->toISOString(),
                updatedAt: $placement->updated_at->utc()->toISOString(),
            ))
            ->all();

        $changes = SubscriptionChangeRecord::query()
            ->where('subscription_id', $record->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionChangeRecord $change): SubscriptionChange => new SubscriptionChange(
                id: (string) $change->id,
                operationId: (string) $change->operation_id,
                subscriptionId: (string) $change->subscription_id,
                action: SubscriptionChangeAction::from((string) $change->action),
                actorKind: SubscriptionActorKind::from((string) $change->actor_kind),
                actorExternalUserId: $change->actor_external_user_id === null
                    ? null
                    : (string) $change->actor_external_user_id,
                reason: $change->reason === null ? null : (string) $change->reason,
                previousStatus: $change->previous_status === null
                    ? null
                    : SubscriptionStatus::from((string) $change->previous_status),
                nextStatus: $change->next_status === null
                    ? null
                    : SubscriptionStatus::from((string) $change->next_status),
                previousProgramId: $change->previous_program_id === null
                    ? null
                    : (string) $change->previous_program_id,
                nextProgramId: $change->next_program_id === null
                    ? null
                    : (string) $change->next_program_id,
                previousIsFixed: $change->previous_is_fixed === null
                    ? null
                    : (bool) $change->previous_is_fixed,
                nextIsFixed: $change->next_is_fixed === null
                    ? null
                    : (bool) $change->next_is_fixed,
                occurredAt: $change->occurred_at->utc()->toISOString(),
                previousPersonalRate: $change->previous_personal_rate === null ? null : (string) $change->previous_personal_rate,
                previousIsMaster: $change->previous_is_master === null ? null : (bool) $change->previous_is_master,
                previousMasterRate: $change->previous_master_rate === null ? null : (string) $change->previous_master_rate,
                nextPersonalRate: $change->next_personal_rate === null ? null : (string) $change->next_personal_rate,
                nextIsMaster: $change->next_is_master === null ? null : (bool) $change->next_is_master,
                nextMasterRate: $change->next_master_rate === null ? null : (string) $change->next_master_rate,
            ))
            ->all();

        return new Subscription(
            id: (string) $record->id,
            externalUserId: (string) $record->external_user_id,
            planId: (string) $record->plan_id,
            origin: SubscriptionOrigin::from((string) $record->origin),
            requiresApproval: $record->requires_approval === null
                ? null
                : (bool) $record->requires_approval,
            status: SubscriptionStatus::from((string) $record->status),
            activatedAt: $record->activated_at?->utc()->toISOString(),
            closedAt: $record->closed_at?->utc()->toISOString(),
            replacesSubscriptionId: $record->replaces_subscription_id === null
                ? null
                : (string) $record->replaces_subscription_id,
            personalRate: (string) $record->personal_rate,
            isMaster: (bool) $record->is_master,
            masterRate: (string) $record->master_rate,
            lockVersion: (int) $record->lock_version,
            placements: $placements,
            changes: $changes,
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function subscriptionAttributes(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'external_user_id' => $subscription->externalUserId,
            'plan_id' => $subscription->planId,
            'origin' => $subscription->origin->value,
            'requires_approval' => $subscription->requiresApproval,
            'status' => $subscription->status->value,
            'activated_at' => $subscription->activatedAt,
            'closed_at' => $subscription->closedAt,
            'replaces_subscription_id' => $subscription->replacesSubscriptionId,
            'personal_rate' => $subscription->personalRate,
            'is_master' => $subscription->isMaster,
            'master_rate' => $subscription->masterRate,
            'lock_version' => $subscription->lockVersion,
            'created_at' => $subscription->createdAt,
            'updated_at' => $subscription->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function placementAttributes(SubscriptionPlacement $placement): array
    {
        return [
            'id' => $placement->id,
            'subscription_id' => $placement->subscriptionId,
            'program_id' => $placement->programId,
            'is_fixed' => $placement->isFixed(),
            'effective_from' => $placement->effectiveFrom,
            'effective_until' => $placement->effectiveUntil,
            'created_at' => $placement->createdAt,
            'updated_at' => $placement->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function changeAttributes(SubscriptionChange $change): array
    {
        return [
            'id' => $change->id,
            'operation_id' => $change->operationId,
            'subscription_id' => $change->subscriptionId,
            'action' => $change->action->value,
            'actor_kind' => $change->actorKind->value,
            'actor_external_user_id' => $change->actorExternalUserId,
            'reason' => $change->reason,
            'previous_status' => $change->previousStatus?->value,
            'next_status' => $change->nextStatus?->value,
            'previous_program_id' => $change->previousProgramId,
            'next_program_id' => $change->nextProgramId,
            'previous_is_fixed' => $change->previousIsFixed,
            'next_is_fixed' => $change->nextIsFixed,
            'occurred_at' => $change->occurredAt,
            'previous_personal_rate' => $change->previousPersonalRate,
            'previous_is_master' => $change->previousIsMaster,
            'previous_master_rate' => $change->previousMasterRate,
            'next_personal_rate' => $change->nextPersonalRate,
            'next_is_master' => $change->nextIsMaster,
            'next_master_rate' => $change->nextMasterRate,
        ];
    }
}
