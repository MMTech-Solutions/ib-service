<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_operator_lists_active_broker_symbols_with_hierarchical_filters(): void
    {
        $moduleId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}/catalog/symbol", [
            'server_group' => 'broker:group:mt5-usd',
            'search' => 'eur',
        ])->assertOk()
            ->assertJsonPath('data.0.reference', 'broker:symbol:eurusd')
            ->assertJsonPath('data.0.currency_code', 'USD')
            ->assertJsonPath('data.0.parents.server_group', 'broker:group:mt5-usd')
            ->assertJsonPath('meta.filters.server_group', 'broker:group:mt5-usd');
    }

    public function test_catalog_requires_modules_permission(): void
    {
        $moduleId = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $this->getJson("/api/ib/v1/admin/modules/{$moduleId}/catalog/symbol")->assertUnauthorized();
    }
}
