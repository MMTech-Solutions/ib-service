<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Repositories;

use App\Features\Modules\Catalog\DTOs\ModuleListQueryData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryPageData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryQueryData;
use App\Features\Modules\Catalog\DTOs\ModulesPageData;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\OperationalControlChange;
use Closure;

interface ModuleRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return list<Module> */
    public function all(): array;

    public function findById(string $id): ?Module;

    public function findByCode(string $code): ?Module;

    public function create(Module $module): void;

    public function update(Module $module, int $expectedLockVersion): void;

    public function deleteIfUnreferenced(Module $module): bool;

    public function appendOperationalChange(OperationalControlChange $change): void;

    public function paginateOperationalHistory(ModuleOperationalHistoryQueryData $query): ModuleOperationalHistoryPageData;

    public function paginate(ModuleListQueryData $query): ModulesPageData;
}
