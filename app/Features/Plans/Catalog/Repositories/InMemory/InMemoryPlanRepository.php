<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\InMemory;

use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\PlanAggregatePageData;
use App\Features\Plans\Catalog\DTOs\PlanListQueryData;
use App\Features\Plans\Catalog\Exceptions\DuplicatePlanCodeException;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Models\Plan;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use Closure;
use Throwable;

final class InMemoryPlanRepository implements PlanRepositoryInterface
{
    /** @var array<string, Plan> */
    private array $plans = [];

    /** @var list<PlanOperationalChange> */
    private array $changes = [];

    public function transaction(Closure $callback): mixed
    {
        $planSnapshot = unserialize(serialize($this->plans), ['allowed_classes' => true]);
        $changeSnapshot = unserialize(serialize($this->changes), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->plans = $planSnapshot;
            $this->changes = $changeSnapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?Plan
    {
        foreach ($this->plans as $plan) {
            if ($plan->id === $id && $plan->deletedAt === null) {
                return $this->copy($plan);
            }
        }

        return null;
    }

    public function findByIdIncludingArchived(string $id): ?Plan
    {
        foreach ($this->plans as $plan) {
            if ($plan->id === $id) {
                return $this->copy($plan);
            }
        }

        return null;
    }

    public function findByCode(string $code): ?Plan
    {
        $plan = $this->plans[$code] ?? null;
        if ($plan === null || $plan->deletedAt !== null) {
            return null;
        }

        return $this->copy($plan);
    }

    public function allActive(): array
    {
        $plans = array_values(array_filter(
            $this->plans,
            static fn (Plan $plan): bool => $plan->isActive && $plan->deletedAt === null,
        ));

        return array_map(fn (Plan $plan): Plan => $this->copy($plan), $plans);
    }

    public function create(Plan $plan): void
    {
        if (isset($this->plans[$plan->code])) {
            throw DuplicatePlanCodeException::forCode($plan->code);
        }

        $this->plans[$plan->code] = $this->copy($plan);
    }

    public function update(Plan $plan, int $expectedLockVersion): void
    {
        $stored = $this->plans[$plan->code] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw PlanConcurrencyException::forPlan($plan->id);
        }

        $plan->lockVersion = $expectedLockVersion + 1;
        $this->plans[$plan->code] = $this->copy($plan);
    }

    public function appendOperationalChange(PlanOperationalChange $change): void
    {
        $this->changes[] = unserialize(serialize($change), ['allowed_classes' => true]);
    }

    public function paginate(PlanListQueryData $query): PlanAggregatePageData
    {
        $filtered = array_values(array_filter($this->plans, static function (Plan $plan) use ($query): bool {
            if ($plan->deletedAt !== null) {
                return false;
            }

            if ($query->search !== null) {
                $needle = mb_strtolower($query->search);
                if (! str_contains(mb_strtolower($plan->code), $needle)
                    && ! str_contains(mb_strtolower($plan->name), $needle)) {
                    return false;
                }
            }

            return $query->isActive === null || $plan->isActive === $query->isActive;
        }));

        usort($filtered, static fn (Plan $a, Plan $b): int => $a->code <=> $b->code);

        $total = count($filtered);
        $offset = ($query->page - 1) * $query->perPage;
        $plans = array_map(
            fn (Plan $plan): Plan => $this->copy($plan),
            array_slice($filtered, $offset, $query->perPage),
        );

        return new PlanAggregatePageData(
            plans: $plans,
            currentPage: $query->page,
            perPage: $query->perPage,
            total: $total,
            lastPage: max(1, (int) ceil($total / max(1, $query->perPage))),
        );
    }

    public function isModuleReferenced(string $moduleId): bool
    {
        foreach ($this->plans as $plan) {
            foreach ($plan->bindings as $binding) {
                if ($binding->moduleId === $moduleId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function copy(Plan $plan): Plan
    {
        /** @var Plan $copy */
        $copy = unserialize(serialize($plan), ['allowed_classes' => true]);

        return $copy;
    }
}
