<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ModuleOperationalJourneyTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_main_m1_operational_journey_is_auditable_and_isolated(): void
    {
        $brokerId = (string) DB::table('modules')->where('code', 'broker')->value('id');
        $independentModule = ModuleRecord::factory()->create(['code' => 'copy-trading']);

        $detail = $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$brokerId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.capabilities')
            ->json('data');

        $paused = $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$brokerId}/pause", [
            'reason' => 'Operational review',
            'lock_version' => $detail['lock_version'],
        ])->assertOk()->assertJsonPath('data.processing_status', 'paused')->json('data');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$brokerId}/operational-history")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'pause')
            ->assertJsonPath('data.0.reason', 'Operational review');

        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$brokerId}/deactivate", [
            'reason' => 'Source disabled',
            'lock_version' => $paused['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.processing_status', 'paused');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$brokerId}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.processing_status', 'paused');

        $independentModule->refresh();
        self::assertTrue($independentModule->is_active);
        self::assertSame('running', $independentModule->processing_status);
    }
}
