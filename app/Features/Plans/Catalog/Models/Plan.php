<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Models;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Plans\Catalog\DTOs\PlanData;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\DTOs\PlanModuleBindingData;
use App\Features\Plans\Catalog\Exceptions\PlanCannotActivateException;
use App\Features\Plans\Catalog\Exceptions\PlanCannotArchiveWhenActiveException;
use App\Features\Plans\Catalog\Exceptions\PlanCannotClearBindingsWhenActiveException;
use Closure;

final class Plan
{
    /**
     * @param  list<PlanModuleBinding>  $bindings
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public bool $requiresApproval,
        public int $lockVersion,
        public array $bindings,
        public readonly string $createdAt,
        public string $updatedAt,
        public ?string $deletedAt,
    ) {}

    /**
     * @param  list<string>  $moduleIds
     */
    public static function create(
        string $id,
        string $code,
        string $name,
        ?string $description,
        array $moduleIds,
        Closure $generateId,
        string $now,
        bool $requiresApproval = true,
    ): self {
        $plan = new self(
            id: $id,
            code: $code,
            name: $name,
            description: $description,
            isActive: false,
            requiresApproval: $requiresApproval,
            lockVersion: 1,
            bindings: [],
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
        );
        $plan->replaceBindings($moduleIds, $generateId, $now);

        return $plan;
    }

    public function updateAdministrativeFields(string $name, ?string $description, string $now): bool
    {
        if ($this->name === $name && $this->description === $description) {
            return false;
        }

        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;

        return true;
    }

    public function updateRequiresApproval(bool $requiresApproval, string $now): bool
    {
        if ($this->requiresApproval === $requiresApproval) {
            return false;
        }

        $this->requiresApproval = $requiresApproval;
        $this->updatedAt = $now;

        return true;
    }

    /**
     * @param  list<string>  $moduleIds
     */
    public function replaceBindings(array $moduleIds, Closure $generateId, string $now): bool
    {
        $normalized = array_values(array_unique($moduleIds));
        $current = array_map(
            static fn (PlanModuleBinding $binding): string => $binding->moduleId,
            $this->bindings,
        );
        sort($current);
        $sorted = $normalized;
        sort($sorted);

        if ($current === $sorted) {
            return false;
        }

        if ($this->isActive && $normalized === []) {
            throw PlanCannotClearBindingsWhenActiveException::forPlan($this->id);
        }

        $this->bindings = array_map(
            fn (string $moduleId): PlanModuleBinding => new PlanModuleBinding(
                id: $generateId(),
                planId: $this->id,
                moduleId: $moduleId,
                createdAt: $now,
            ),
            $normalized,
        );
        $this->updatedAt = $now;

        return true;
    }

    /**
     * @param  list<ModuleSummaryData>  $modules
     */
    public function activate(array $modules, string $now): bool
    {
        if ($this->isActive) {
            return false;
        }

        if (! $this->hasOperationalModule($modules)) {
            throw PlanCannotActivateException::missingOperationalModule($this->id);
        }

        $this->isActive = true;
        $this->updatedAt = $now;

        return true;
    }

    public function deactivate(string $now): bool
    {
        if (! $this->isActive) {
            return false;
        }

        $this->isActive = false;
        $this->updatedAt = $now;

        return true;
    }

    public function archive(string $now): bool
    {
        if ($this->deletedAt !== null) {
            return false;
        }

        if ($this->isActive) {
            throw PlanCannotArchiveWhenActiveException::forPlan($this->id);
        }

        $this->deletedAt = $now;
        $this->updatedAt = $now;

        return true;
    }

    /**
     * @param  list<ModuleSummaryData>  $modules
     */
    public function hasOperationalModule(array $modules): bool
    {
        $boundIds = array_map(
            static fn (PlanModuleBinding $binding): string => $binding->moduleId,
            $this->bindings,
        );

        foreach ($modules as $module) {
            if ($module->is_active && in_array($module->id, $boundIds, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function moduleIds(): array
    {
        return array_map(
            static fn (PlanModuleBinding $binding): string => $binding->moduleId,
            $this->bindings,
        );
    }

    /**
     * @param  array<string, ModuleSummaryData>  $modulesById
     */
    public function toData(array $modulesById): PlanData
    {
        return new PlanData(
            id: $this->id,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            is_active: $this->isActive,
            requires_approval: $this->requiresApproval,
            lock_version: $this->lockVersion,
            modules: $this->moduleData($modulesById),
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }

    /**
     * @param  array<string, ModuleSummaryData>  $modulesById
     */
    public function toDetailData(array $modulesById): PlanDetailData
    {
        $list = $this->toData($modulesById);

        return new PlanDetailData(
            id: $list->id,
            code: $list->code,
            name: $list->name,
            description: $list->description,
            is_active: $list->is_active,
            requires_approval: $list->requires_approval,
            lock_version: $list->lock_version,
            modules: $list->modules,
            created_at: $list->created_at,
            updated_at: $list->updated_at,
        );
    }

    /**
     * @param  array<string, ModuleSummaryData>  $modulesById
     * @return list<PlanModuleBindingData>
     */
    private function moduleData(array $modulesById): array
    {
        $bindings = $this->bindings;
        usort(
            $bindings,
            static fn (PlanModuleBinding $a, PlanModuleBinding $b): int => $a->moduleId <=> $b->moduleId,
        );

        return array_map(
            static function (PlanModuleBinding $binding) use ($modulesById): PlanModuleBindingData {
                $module = $modulesById[$binding->moduleId] ?? null;

                return new PlanModuleBindingData(
                    id: $binding->id,
                    module_id: $binding->moduleId,
                    code: $module?->code ?? '',
                    name: $module?->name ?? '',
                    is_active: $module?->is_active ?? false,
                    processing_status: $module?->processing_status ?? 'running',
                    created_at: $binding->createdAt,
                );
            },
            $bindings,
        );
    }
}
