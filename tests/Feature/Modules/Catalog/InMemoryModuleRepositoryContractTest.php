<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\Repositories\InMemory\InMemoryModuleRepository;
use App\Features\Modules\Catalog\Repositories\NoModuleReferences;
use Tests\Contracts\ModuleRepositoryContract;

final class InMemoryModuleRepositoryContractTest extends ModuleRepositoryContract
{
    private InMemoryModuleRepository $moduleRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleRepository = new InMemoryModuleRepository(new NoModuleReferences);
    }

    protected function repository(): ModuleRepositoryInterface
    {
        return $this->moduleRepository;
    }
}
