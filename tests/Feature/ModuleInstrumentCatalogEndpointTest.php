<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ModuleInstrumentCatalogEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        Http::fake(fn () => Http::response([
            'data' => [[
                'reference' => '00000000-0000-7000-8000-000000000005',
                'type' => 'symbol',
                'name' => 'EURUSD',
                'parents' => ['platform' => '00000000-0000-7000-8000-000000000001', 'trading_server' => '00000000-0000-7000-8000-000000000002', 'server_group' => '00000000-0000-7000-8000-000000000003'],
                'currency_code' => 'USD',
            ]],
            'meta' => ['pagination' => ['total' => 2, 'current_page' => 1, 'per_page' => 1]],
        ]));
    }

    public function test_operator_lists_active_broker_symbols_with_hierarchical_filters(): void
    {
        $moduleId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}/catalog/symbol", [
            'server_group' => 'broker:server_group:00000000-0000-7000-8000-000000000003',
            'search' => 'eur',
        ])->assertOk()
            ->assertJsonPath('data.0.reference', 'broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005')
            ->assertJsonPath('data.0.currency_code', 'USD')
            ->assertJsonPath('data.0.parents.server_group', 'broker:server_group:00000000-0000-7000-8000-000000000003')
            ->assertJsonPath('meta.filters.server_group', 'broker:server_group:00000000-0000-7000-8000-000000000003');
    }

    public function test_catalog_requires_modules_permission(): void
    {
        $moduleId = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $this->assertGatewayAuthGuards('GET', "/api/ib/v1/admin/modules/{$moduleId}/catalog/symbol");
    }

    public function test_catalog_validates_type_and_preserves_paginated_data_shape(): void
    {
        $moduleId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}/catalog/symbol", [
            'per_page' => 1,
        ])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.per_page', 1);

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}/catalog/unsupported")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_catalog_rejects_missing_inactive_and_unsupported_modules(): void
    {
        $brokerId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');

        $this->gatewayJson('GET', '/api/ib/v1/admin/modules/01993ac2-8750-73fd-b102-ba24fb06d8bf/catalog/symbol')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');

        ModuleRecord::query()->whereKey($brokerId)->update(['is_active' => false]);
        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$brokerId}/catalog/symbol")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MODULE_INACTIVE');

        $unsupportedModule = ModuleRecord::factory()->create(['code' => 'prop-firm']);
        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$unsupportedModule->id}/catalog/symbol")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'UNSUPPORTED_INSTRUMENT_CATALOG_CAPABILITY');
    }
}
