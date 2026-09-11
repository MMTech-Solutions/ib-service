<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Repositories;

use App\Features\Modules\Catalog\Contracts\Data\ModuleListQueryData;
use App\Features\Modules\Catalog\Contracts\Data\ModulesPageData;
use App\Features\Modules\Catalog\Models\Module;
use Closure;

interface ModuleRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @return list<Module> */
    public function all(): array;

    public function findByCode(string $code): ?Module;

    public function create(Module $module): void;

    public function update(Module $module, int $expectedLockVersion): void;

    public function deleteIfUnreferenced(Module $module): bool;

    public function paginate(ModuleListQueryData $query): ModulesPageData;
}
