<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ListModuleOperationalHistoryEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_list_paginated_history_with_actor_and_state_snapshots(): void
    {
        $moduleId = $this->brokerId();
        $pause = $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$moduleId}/pause", [
            'reason' => 'Risk review',
            'lock_version' => 1,
        ])->assertOk()->json('data.module');
        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$moduleId}/resume", [
            'reason' => 'Risk cleared',
            'lock_version' => $pause['lock_version'],
        ])->assertOk();

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}/operational-history?per_page=1")
            ->assertOk()
            ->assertJsonPath('data.entries.0.action', 'resume')
            ->assertJsonPath('data.entries.0.actor_iam_id', $this->authorizedSub())
            ->assertJsonPath('data.entries.0.previous_processing_status', 'paused')
            ->assertJsonPath('data.entries.0.next_processing_status', 'running')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_history_rejects_unknown_module_and_invalid_pagination(): void
    {
        $this->gatewayJson('GET', '/api/ib/v1/admin/modules/'.Str::uuid7().'/operational-history')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$this->brokerId()}/operational-history?per_page=101")
            ->assertUnprocessable();
    }

    public function test_history_route_enforces_gateway_identity_and_permission(): void
    {
        $this->assertGatewayAuthGuards('GET', "/api/ib/v1/admin/modules/{$this->brokerId()}/operational-history");
    }

    private function brokerId(): string
    {
        return (string) DB::table('modules')->where('code', 'broker')->value('id');
    }
}
