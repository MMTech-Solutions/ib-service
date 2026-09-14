<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Contracts\Repositories;

use App\Features\Plans\Catalog\DTOs\PlanAggregatePageData;
use App\Features\Plans\Catalog\DTOs\PlanListQueryData;
use App\Features\Plans\Catalog\Models\Plan;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use Closure;

interface PlanRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?Plan;

    public function findByIdIncludingArchived(string $id): ?Plan;

    public function findByCode(string $code): ?Plan;

    /** @return list<Plan> */
    public function allActive(): array;

    public function create(Plan $plan): void;

    public function update(Plan $plan, int $expectedLockVersion): void;

    public function appendOperationalChange(PlanOperationalChange $change): void;

    public function paginate(PlanListQueryData $query): PlanAggregatePageData;

    public function isModuleReferenced(string $moduleId): bool;
}
