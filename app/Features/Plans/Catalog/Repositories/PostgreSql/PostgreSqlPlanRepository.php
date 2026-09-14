<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql;

use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\PlanAggregatePageData;
use App\Features\Plans\Catalog\DTOs\PlanListQueryData;
use App\Features\Plans\Catalog\Exceptions\DuplicatePlanCodeException;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Models\Plan;
use App\Features\Plans\Catalog\Models\PlanModuleBinding;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanOperationalChangeRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlPlanRepository implements PlanRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?Plan
    {
        $record = PlanRecord::query()->current()->with('bindings')->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByIdIncludingArchived(string $id): ?Plan
    {
        $record = PlanRecord::query()->with('bindings')->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByCode(string $code): ?Plan
    {
        $record = PlanRecord::query()->current()->with('bindings')->where('code', $code)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function allActive(): array
    {
        return PlanRecord::query()
            ->current()
            ->with('bindings')
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (PlanRecord $record): Plan => $this->hydrate($record))
            ->all();
    }

    public function create(Plan $plan): void
    {
        try {
            $this->connection->transaction(function () use ($plan): void {
                PlanRecord::query()->create($this->planAttributes($plan));
                $this->syncBindings($plan);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicatePlanCodeException::forCode($plan->code);
        }
    }

    public function update(Plan $plan, int $expectedLockVersion): void
    {
        $nextLockVersion = $expectedLockVersion + 1;
        $affected = PlanRecord::query()
            ->whereKey($plan->id)
            ->where('lock_version', $expectedLockVersion)
            ->update([
                'name' => $plan->name,
                'description' => $plan->description,
                'is_active' => $plan->isActive,
                'lock_version' => $nextLockVersion,
                'updated_at' => $plan->updatedAt,
                'deleted_at' => $plan->deletedAt,
            ]);

        if ($affected !== 1) {
            throw PlanConcurrencyException::forPlan($plan->id);
        }

        $this->syncBindings($plan);
        $plan->lockVersion = $nextLockVersion;
    }

    public function appendOperationalChange(PlanOperationalChange $change): void
    {
        PlanOperationalChangeRecord::query()->create([
            'id' => $change->id,
            'plan_id' => $change->planId,
            'action' => $change->action->value,
            'actor_kind' => $change->actorKind->value,
            'actor_iam_id' => $change->actorIamId,
            'reason' => $change->reason,
            'previous_is_active' => $change->previousIsActive,
            'next_is_active' => $change->nextIsActive,
            'cause_event_id' => $change->causeEventId,
            'cause_module_id' => $change->causeModuleId,
            'initiating_actor_iam_id' => $change->initiatingActorIamId,
            'occurred_at' => $change->occurredAt,
        ]);
    }

    public function paginate(PlanListQueryData $query): PlanAggregatePageData
    {
        $builder = PlanRecord::query()
            ->current()
            ->with('bindings')
            ->when($query->search !== null, function ($builder) use ($query): void {
                $search = '%'.mb_strtolower($query->search).'%';
                $builder->where(function ($nested) use ($search): void {
                    $nested->whereRaw('LOWER(code) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$search]);
                });
            })
            ->when($query->isActive !== null, fn ($builder) => $builder->where('is_active', $query->isActive))
            ->orderBy('code');

        $paginator = $builder->paginate($query->perPage, ['*'], 'page', $query->page);
        $plans = collect($paginator->items())
            ->map(fn (PlanRecord $record): Plan => $this->hydrate($record))
            ->all();

        return new PlanAggregatePageData(
            plans: $plans,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function isModuleReferenced(string $moduleId): bool
    {
        return PlanModuleBindingRecord::query()->where('module_id', $moduleId)->exists();
    }

    private function syncBindings(Plan $plan): void
    {
        PlanModuleBindingRecord::query()->where('plan_id', $plan->id)->delete();
        $bindings = $this->bindingAttributes($plan);
        if ($bindings !== []) {
            PlanModuleBindingRecord::query()->insert($bindings);
        }
    }

    private function hydrate(PlanRecord $record): Plan
    {
        return new Plan(
            id: (string) $record->id,
            code: (string) $record->code,
            name: (string) $record->name,
            description: $record->description === null ? null : (string) $record->description,
            isActive: (bool) $record->is_active,
            lockVersion: (int) $record->lock_version,
            bindings: $record->bindings->map(
                static fn (PlanModuleBindingRecord $binding): PlanModuleBinding => new PlanModuleBinding(
                    id: (string) $binding->id,
                    planId: (string) $binding->plan_id,
                    moduleId: (string) $binding->module_id,
                    createdAt: $binding->created_at->utc()->toISOString(),
                )
            )->all(),
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
            deletedAt: $record->deleted_at?->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function planAttributes(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'is_active' => $plan->isActive,
            'lock_version' => $plan->lockVersion,
            'created_at' => $plan->createdAt,
            'updated_at' => $plan->updatedAt,
            'deleted_at' => $plan->deletedAt,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bindingAttributes(Plan $plan): array
    {
        return array_map(
            static fn (PlanModuleBinding $binding): array => [
                'id' => $binding->id,
                'plan_id' => $plan->id,
                'module_id' => $binding->moduleId,
                'created_at' => $binding->createdAt,
            ],
            $plan->bindings,
        );
    }
}
