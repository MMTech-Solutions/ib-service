<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ProgramCatalogEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_manage_programs_under_a_plan(): void
    {
        $brokerId = $this->brokerId();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
            'module_ids' => [$brokerId],
        ])->assertCreated()->json('data');

        $created = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'description' => 'Entry',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'basic')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.module_ids', [])
            ->json('data');

        $advanced = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'module_ids' => [$brokerId],
        ])->assertCreated()
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.module_ids.0', $brokerId)
            ->json('data');

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/programs")
            ->assertOk()
            ->assertJsonPath('data.0.code', 'basic')
            ->assertJsonPath('data.1.code', 'advanced');

        $updated = $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/programs/{$created['id']}", [
            'module_ids' => [$brokerId],
            'lock_version' => $created['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.module_ids.0', $brokerId)
            ->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs/reorder", [
            'program_ids' => [$advanced['id'], $updated['id']],
        ])->assertOk()
            ->assertJsonPath('data.0.code', 'advanced')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.code', 'basic')
            ->assertJsonPath('data.1.position', 2);

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/programs/{$updated['id']}")
            ->assertOk()
            ->assertJsonPath('data.position', 2);

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Duplicate',
        ])->assertConflict()->assertJsonPath('error.code', 'PROGRAM_CODE_CONFLICT');
    }

    public function test_program_module_selection_must_belong_to_the_plan(): void
    {
        $brokerId = $this->brokerId();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
            'module_ids' => [$brokerId],
        ])->assertCreated()->json('data');

        $unknownModule = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'module_ids' => [$unknownModule],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'MODULE_NOT_ENABLED_ON_PLAN');
    }

    public function test_archived_plans_reject_program_mutations(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
        ])->assertCreated()->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan['id']}", [
            'reason' => 'Retired',
            'lock_version' => $plan['lock_version'],
        ])->assertNoContent();

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_ARCHIVED');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/programs/{$program['id']}", [
            'name' => 'Renamed',
            'lock_version' => $program['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_ARCHIVED');

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/programs")
            ->assertOk()
            ->assertJsonPath('data.0.code', 'basic');
    }

    public function test_program_routes_enforce_gateway_identity_and_permission(): void
    {
        $planId = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $this->getJson("/api/ib/v1/admin/plans/{$planId}/programs")->assertUnauthorized();
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/plans/{$planId}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
        ]);
    }

    public function test_program_concurrency_conflict_is_reported(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
        ])->assertCreated()->json('data');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/programs/{$program['id']}", [
            'name' => 'First writer',
            'lock_version' => $program['lock_version'],
        ])->assertOk();

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/programs/{$program['id']}", [
            'name' => 'Stale writer',
            'lock_version' => $program['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'PROGRAM_CONCURRENCY_CONFLICT');
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
