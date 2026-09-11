<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\InMemory;

use App\Features\Modules\Catalog\Contracts\Data\ModuleListQueryData;
use App\Features\Modules\Catalog\Contracts\Data\ModulesPageData;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\Exceptions\DuplicateModuleCodeException;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Models\Module;
use Closure;
use Throwable;

final class InMemoryModuleRepository implements ModuleRepositoryInterface
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function __construct(private readonly ModuleReferenceGuardInterface $referenceGuard) {}

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->modules), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->modules = $snapshot;
            throw $throwable;
        }
    }

    public function all(): array
    {
        $modules = array_values($this->modules);
        usort($modules, static fn (Module $a, Module $b): int => $a->code <=> $b->code);

        return array_map(fn (Module $module): Module => $this->copy($module), $modules);
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

        return true;
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
