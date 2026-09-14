<?php

declare(strict_types=1);

namespace Tests\Feature\Plans\Catalog;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use Tests\Contracts\PlanRepositoryContract;

final class PostgreSqlPlanRepositoryContractTest extends PlanRepositoryContract
{
    /** @var list<string> */
    private array $moduleIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('modules:sync')->assertExitCode(0);
        $brokerId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');
        $extra = ModuleRecord::factory()->create([
            'code' => 'copy-trading',
            'name' => 'Copy Trading',
            'description' => null,
            'is_active' => true,
            'processing_status' => 'running',
            'lock_version' => 1,
        ]);
        $this->moduleIds = [$brokerId, (string) $extra->id];
    }

    protected function repository(): PlanRepositoryInterface
    {
        return app(PlanRepositoryFactory::class)->make('postgresql');
    }

    protected function moduleIds(): array
    {
        return $this->moduleIds;
    }
}
