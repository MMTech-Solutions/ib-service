<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\InMemory;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\DTOs\ModuleListQueryData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryPageData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryQueryData;
use App\Features\Modules\Catalog\DTOs\ModulesPageData;
use App\Features\Modules\Catalog\Exceptions\DuplicateModuleCodeException;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\OperationalControlChange;
use Closure;
use Throwable;

final class InMemoryModuleRepository implements ModuleRepositoryInterface
{
    /** @var array<string, Module> */
    private array $modules = [];

    /** @var list<OperationalControlChange> */
    private array $changes = [];

    public function __construct(private readonly ModuleReferenceGuardInterface $referenceGuard) {}

    public function transaction(Closure $callback): mixed
    {
        $moduleSnapshot = unserialize(serialize($this->modules), ['allowed_classes' => true]);
        $changeSnapshot = unserialize(serialize($this->changes), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->modules = $moduleSnapshot;
            $this->changes = $changeSnapshot;
            throw $throwable;
        }
    }

    public function all(): array
    {
        $modules = array_values($this->modules);
        usort($modules, static fn (Module $a, Module $b): int => $a->code <=> $b->code);

        return array_map(fn (Module $module): Module => $this->copy($module), $modules);
    }

    public function findById(string $id): ?Module
    {
        foreach ($this->modules as $module) {
            if ($module->id === $id) {
                return $this->copy($module);
            }
        }

        return null;
    }

    public function findByCode(string $code): ?Module
    {
        return isset($this->modules[$code]) ? $this->copy($this->modules[$code]) : null;
    }

    public function create(Module $module): void
    {
        if (isset($this->modules[$module->code])) {
            throw DuplicateModuleCodeException::forCode($module->code);
        }

        $this->modules[$module->code] = $this->copy($module);
    }

    public function update(Module $module, int $expectedLockVersion): void
    {
        $stored = $this->modules[$module->code] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw ModuleConcurrencyException::forModule($module->id);
        }

        $module->lockVersion = $expectedLockVersion + 1;
        $this->modules[$module->code] = $this->copy($module);
    }

    public function deleteIfUnreferenced(Module $module): bool
    {
        if ($this->referenceGuard->isReferenced($module->id)) {
            return false;
        }

        unset($this->modules[$module->code]);
        $this->changes = array_values(array_filter(
            $this->changes,
            static fn (OperationalControlChange $change): bool => $change->moduleId !== $module->id,
        ));

        return true;
    }

    public function appendOperationalChange(OperationalControlChange $change): void
    {
        $this->changes[] = unserialize(serialize($change), ['allowed_classes' => true]);
    }

    public function paginateOperationalHistory(ModuleOperationalHistoryQueryData $query): ModuleOperationalHistoryPageData
    {
        $filtered = array_values(array_filter(
            $this->changes,
            static fn (OperationalControlChange $change): bool => $change->moduleId === $query->moduleId,
        ));

        usort($filtered, static function (OperationalControlChange $a, OperationalControlChange $b): int {
            $comparison = $b->occurredAt <=> $a->occurredAt;

            return $comparison !== 0 ? $comparison : $b->id <=> $a->id;
        });

        $total = count($filtered);
        $offset = ($query->page - 1) * $query->perPage;
        $entries = array_map(
            static fn (OperationalControlChange $change) => $change->toData(),
            array_slice($filtered, $offset, $query->perPage),
        );

        return new ModuleOperationalHistoryPageData(
            entries: $entries,
            currentPage: $query->page,
            perPage: $query->perPage,
            total: $total,
            lastPage: max(1, (int) ceil($total / max(1, $query->perPage))),
        );
    }

    public function paginate(ModuleListQueryData $query): ModulesPageData
    {
        $filtered = array_values(array_filter($this->all(), static function (Module $module) use ($query): bool {
            if ($query->search !== null) {
                $needle = mb_strtolower($query->search);
                if (! str_contains(mb_strtolower($module->code), $needle)
                    && ! str_contains(mb_strtolower($module->name), $needle)) {
                    return false;
                }
            }

            if ($query->isActive !== null && $module->isActive !== $query->isActive) {
                return false;
            }

            return $query->processingStatus === null
                || $module->processingStatus->value === $query->processingStatus;
        }));

        $total = count($filtered);
        $offset = ($query->page - 1) * $query->perPage;
        $modules = array_map(
            static fn (Module $module) => $module->toData(),
            array_slice($filtered, $offset, $query->perPage),
        );

        return new ModulesPageData(
            modules: $modules,
            currentPage: $query->page,
            perPage: $query->perPage,
            total: $total,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    private function copy(Module $module): Module
    {
        /** @var Module $copy */
        $copy = unserialize(serialize($module), ['allowed_classes' => true]);

        return $copy;
    }
}
