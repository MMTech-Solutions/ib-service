<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use Tests\Contracts\ModuleRepositoryContract;

final class PostgreSqlModuleRepositoryContractTest extends ModuleRepositoryContract
{
    protected function repository(): ModuleRepositoryInterface
    {
        return app(ModuleRepositoryFactory::class)->make('postgresql');
    }
}
