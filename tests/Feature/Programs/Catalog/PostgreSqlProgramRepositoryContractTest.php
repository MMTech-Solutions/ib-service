<?php

declare(strict_types=1);

namespace Tests\Feature\Programs\Catalog;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use Tests\Contracts\ProgramRepositoryContract;

final class PostgreSqlProgramRepositoryContractTest extends ProgramRepositoryContract
{
    private string $planId;

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
        $this->planId = (string) PlanRecord::factory()->create()->id;
    }

    protected function repository(): ProgramRepositoryInterface
    {
        return app(ProgramRepositoryFactory::class)->make('postgresql');
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function moduleIds(): array
    {
        return $this->moduleIds;
    }
}
