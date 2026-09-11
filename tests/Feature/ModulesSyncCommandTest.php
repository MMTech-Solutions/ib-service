<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Contracts\Data\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleDefinitionData;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Catalog\UseCases\SyncModulesUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModulesSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_active_broker_catalog_and_is_idempotent(): void
    {
        $this->artisan('modules:sync')->assertExitCode(0);

        $module = DB::table('modules')->where('code', 'broker')->first();
        self::assertNotNull($module);
        self::assertTrue((bool) $module->is_active);
        self::assertSame('running', $module->processing_status);
        self::assertSame(1, (int) $module->lock_version);
        self::assertSame(
            ['closed_trading_volume', 'deposits'],
            DB::table('module_capabilities')->where('module_id', $module->id)->orderBy('code')->pluck('code')->all(),
        );

        $updatedAt = $module->updated_at;
        $this->artisan('modules:sync')->assertExitCode(0);

        $moduleAfterSecondSync = DB::table('modules')->where('code', 'broker')->first();
        self::assertSame(1, (int) $moduleAfterSecondSync->lock_version);
        self::assertSame($updatedAt, $moduleAfterSecondSync->updated_at);
    }

    public function test_it_preserves_administrative_module_fields(): void
    {
        $this->artisan('modules:sync')->assertExitCode(0);
        DB::table('modules')->where('code', 'broker')->update([
            'name' => 'Broker Custom',
            'description' => 'Managed by administration.',
            'is_active' => false,
            'processing_status' => 'paused',
        ]);

        $this->artisan('modules:sync')->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'code' => 'broker',
            'name' => 'Broker Custom',
            'description' => 'Managed by administration.',
            'is_active' => false,
            'processing_status' => 'paused',
        ]);
    }

    public function test_it_inactivates_and_reactivates_code_governed_capabilities(): void
    {
        $this->artisan('modules:sync')->assertExitCode(0);
        $this->syncWithRegistry(new ModuleDefinitionRegistry([
            new ModuleDefinitionData(
                code: 'broker',
                name: 'Ignored for existing modules',
                description: null,
                capabilities: [
                    new ModuleCapabilityDefinitionData('deposits', 'Deposits renamed', 'Updated description.'),
                ],
            ),
        ]));
        $this->assertDatabaseHas('module_capabilities', ['code' => 'deposits', 'name' => 'Deposits renamed', 'is_active' => true]);
        $this->assertDatabaseHas('module_capabilities', ['code' => 'closed_trading_volume', 'is_active' => false]);

        $this->syncWithRegistry(new ModuleDefinitionRegistry);
        $this->assertDatabaseHas('module_capabilities', ['code' => 'closed_trading_volume', 'is_active' => true]);
    }

    public function test_an_absent_module_is_deactivated_and_is_not_reactivated_automatically(): void
    {
        $this->artisan('modules:sync')->assertExitCode(0);
        $this->syncWithRegistry(new ModuleDefinitionRegistry([]));
        $this->assertDatabaseHas('modules', ['code' => 'broker', 'is_active' => false]);

        $this->syncWithRegistry(new ModuleDefinitionRegistry);
        $this->assertDatabaseHas('modules', ['code' => 'broker', 'is_active' => false]);
    }

    public function test_prune_requires_force_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->artisan('modules:sync --prune')->assertExitCode(1);
        } finally {
            app()->detectEnvironment(static fn (): string => $originalEnvironment);
        }
    }

    public function test_prune_deletes_safe_modules_and_reports_protected_modules(): void
    {
        $this->artisan('modules:sync')->assertExitCode(0);
        $safe = ModuleRecord::factory()->create(['code' => 'safe-legacy']);
        $protected = ModuleRecord::factory()->create(['code' => 'protected-legacy']);

        $this->app->instance(ModuleReferenceGuardInterface::class, new class((string) $protected->id) implements ModuleReferenceGuardInterface
        {
            public function __construct(private readonly string $protectedId) {}

            public function isReferenced(string $moduleId): bool
            {
                return $moduleId === $this->protectedId;
            }
        });
        $this->app->forgetInstance('modules.repositories.postgresql');

        $this->artisan('modules:sync --prune --force')->assertExitCode(2);

        $this->assertDatabaseMissing('modules', ['id' => $safe->id]);
        $this->assertDatabaseHas('modules', ['id' => $protected->id, 'is_active' => false]);
    }

    private function syncWithRegistry(ModuleDefinitionRegistry $registry): void
    {
        $useCase = new SyncModulesUseCase(
            app(ModuleRepositoryFactory::class),
            $registry,
        );

        $useCase->execute();
    }
}
