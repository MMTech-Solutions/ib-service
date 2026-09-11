<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ModuleOperationalControlEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_apply_all_independent_operational_transitions_with_audit(): void
    {
        $moduleId = $this->brokerId();

        $paused = $this->operate($moduleId, 'pause', 1, 'Risk review')
            ->assertOk()
            ->assertJsonPath('data.module.processing_status', 'paused')
            ->assertJsonPath('data.module.is_active', true)
            ->json('data.module');

        $deactivated = $this->operate($moduleId, 'deactivate', $paused['lock_version'], 'Disable source')
            ->assertOk()
            ->assertJsonPath('data.module.processing_status', 'paused')
            ->assertJsonPath('data.module.is_active', false)
            ->json('data.module');

        $resumed = $this->operate($moduleId, 'resume', $deactivated['lock_version'], 'Prepare recovery')
            ->assertOk()
            ->assertJsonPath('data.module.processing_status', 'running')
            ->assertJsonPath('data.module.is_active', false)
            ->json('data.module');

        $this->operate($moduleId, 'activate', $resumed['lock_version'], 'Source recovered')
            ->assertOk()
            ->assertJsonPath('data.module.processing_status', 'running')
            ->assertJsonPath('data.module.is_active', true)
            ->assertJsonPath('data.module.lock_version', 5);

        $this->assertDatabaseCount('module_operational_changes', 4);
        $this->assertDatabaseHas('module_operational_changes', [
            'module_id' => $moduleId,
            'action' => 'pause',
            'actor_iam_id' => $this->authorizedSub(),
            'reason' => 'Risk review',
            'previous_processing_status' => 'running',
            'next_processing_status' => 'paused',
        ]);
    }

    public function test_repeated_operation_is_a_no_op_without_duplicate_history(): void
    {
        $moduleId = $this->brokerId();

        $this->operate($moduleId, 'activate', 1, 'Already active')
            ->assertOk()
            ->assertJsonPath('data.module.lock_version', 1);

        $this->assertDatabaseCount('module_operational_changes', 0);
    }

    public function test_control_requires_reason_and_current_lock_version(): void
    {
        $moduleId = $this->brokerId();

        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$moduleId}/pause", [
            'lock_version' => 1,
        ])->assertUnprocessable();
        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$moduleId}/pause", [
            'reason' => '   ',
            'lock_version' => 1,
        ])->assertUnprocessable();

        DB::table('modules')->where('id', $moduleId)->update(['lock_version' => 2]);
        $this->operate($moduleId, 'pause', 1, 'Stale writer')
            ->assertConflict()
            ->assertJsonPath('error.code', 'MODULE_CONCURRENCY_CONFLICT');
    }

    /** @return iterable<string, array{string}> */
    public static function operationalActionProvider(): iterable
    {
        yield 'activate' => ['activate'];
        yield 'deactivate' => ['deactivate'];
        yield 'pause' => ['pause'];
        yield 'resume' => ['resume'];
    }

    #[DataProvider('operationalActionProvider')]
    public function test_every_control_route_enforces_gateway_identity_and_permission(string $action): void
    {
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/modules/{$this->brokerId()}/{$action}", [
            'reason' => 'Operational reason',
            'lock_version' => 1,
        ]);
    }

    private function operate(string $moduleId, string $action, int $lockVersion, string $reason): TestResponse
    {
        return $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$moduleId}/{$action}", [
            'reason' => $reason,
            'lock_version' => $lockVersion,
        ]);
    }

    private function brokerId(): string
    {
        return (string) DB::table('modules')->where('code', 'broker')->value('id');
    }
}
