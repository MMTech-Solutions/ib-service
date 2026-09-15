<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class RuleAssignmentEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_manage_rule_assignments(): void
    {
        $fixture = $this->createAssignableFixture();

        $created = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertCreated()
            ->assertJsonPath('data.scope_type', 'all')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.rule_version_id', $fixture['version_one_id'])
            ->json('data');

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments?active=1",
        )->assertOk()
            ->assertJsonPath('data.0.id', $created['id'])
            ->assertJsonPath('meta.pagination.total', 1);

        $replaced = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$created['id']}/replace",
            [
                'rule_version_id' => $fixture['version_two_id'],
                'lock_version' => $created['lock_version'],
            ],
        )->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.rule_version_id', $fixture['version_two_id'])
            ->json('data');

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
        )->assertOk()
            ->assertJsonPath('meta.pagination.total', 2);

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$created['id']}",
        )->assertOk()
            ->assertJsonPath('data.active', false);

        $withdrawn = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$replaced['id']}/withdraw",
            ['lock_version' => $replaced['lock_version']],
        )->assertOk()
            ->assertJsonPath('data.active', false)
            ->json('data');

        self::assertNotNull($withdrawn['ends_at']);
    }

    public function test_assignment_rejects_draft_foreign_program_and_unselected_module(): void
    {
        $fixture = $this->createAssignableFixture(includeDraft: true);

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['draft_version_id'],
            ],
        )->assertUnprocessable()->assertJsonPath('error.code', 'RULE_VERSION_NOT_ASSIGNABLE');

        $otherPlan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'other',
            'name' => 'Other',
            'module_ids' => [$fixture['module_id']],
        ])->assertCreated()->json('data');
        $foreignProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$otherPlan['id']}/programs", [
            'code' => 'foreign',
            'name' => 'Foreign',
            'entry_threshold' => 0,
            'module_ids' => [$fixture['module_id']],
        ])->assertCreated()->json('data');

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $foreignProgram['id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertNotFound()->assertJsonPath('error.code', 'PROGRAM_NOT_FOUND');

        $emptyProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs", [
            'code' => 'empty',
            'name' => 'Empty',
            'entry_threshold' => 100,
            'module_ids' => [],
        ])->assertCreated()->json('data');

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $emptyProgram['id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertUnprocessable()->assertJsonPath('error.code', 'MODULE_NOT_SELECTED_ON_PROGRAM');
    }

    public function test_replace_rejects_when_program_no_longer_selects_module(): void
    {
        $fixture = $this->createAssignableFixture();
        $created = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertCreated()->json('data');

        $program = $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs/{$fixture['program_id']}",
        )->assertOk()->json('data');

        $this->gatewayJson(
            'PATCH',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs/{$fixture['program_id']}",
            [
                'module_ids' => [],
                'lock_version' => $program['lock_version'],
            ],
        )->assertOk()->assertJsonPath('data.module_ids', []);

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$created['id']}/replace",
            [
                'rule_version_id' => $fixture['version_two_id'],
                'lock_version' => $created['lock_version'],
            ],
        )->assertUnprocessable()->assertJsonPath('error.code', 'MODULE_NOT_SELECTED_ON_PROGRAM');

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$created['id']}",
        )->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.rule_version_id', $fixture['version_one_id'])
            ->assertJsonPath('data.lock_version', $created['lock_version']);

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
        )->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $created['id']);
    }

    public function test_archived_plans_reject_assignment_mutations(): void
    {
        $fixture = $this->createAssignableFixture();
        $created = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertCreated()->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$fixture['plan_id']}", [
            'reason' => 'Retired',
            'lock_version' => $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$fixture['plan_id']}")
                ->assertOk()
                ->json('data.lock_version'),
        ])->assertNoContent();

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_two_id'],
            ],
        )->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_ARCHIVED');

        $this->gatewayJson(
            'GET',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
        )->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);
    }

    public function test_assignment_routes_enforce_gateway_identity_and_permission(): void
    {
        $planId = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $ruleId = '01993ac2-8750-73fd-b102-ba24fb06d8bf';
        $this->getJson("/api/ib/v1/admin/plans/{$planId}/rules/{$ruleId}/assignments")->assertUnauthorized();
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/plans/{$planId}/rules/{$ruleId}/assignments", [
            'program_id' => $planId,
            'module_id' => $ruleId,
            'rule_version_id' => $ruleId,
        ]);
    }

    public function test_duplicate_active_assignment_is_rejected(): void
    {
        $fixture = $this->createAssignableFixture();
        $payload = [
            'program_id' => $fixture['program_id'],
            'module_id' => $fixture['module_id'],
            'rule_version_id' => $fixture['version_one_id'],
        ];

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            $payload,
        )->assertCreated();

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            $payload,
        )->assertConflict()->assertJsonPath('error.code', 'RULE_ASSIGNMENT_ACTIVE_CONFLICT');
    }

    /**
     * @return array{
     *     plan_id: string,
     *     plan_lock_version: int,
     *     program_id: string,
     *     module_id: string,
     *     rule_id: string,
     *     version_one_id: string,
     *     version_two_id: string,
     *     draft_version_id?: string
     * }
     */
    private function createAssignableFixture(bool $includeDraft = false): array
    {
        $moduleId = $this->brokerId();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
            'module_ids' => [$moduleId],
        ])->assertCreated()->json('data');

        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'entry_threshold' => 0,
            'module_ids' => [$moduleId],
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');

        $draftOne = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => 'lot',
                'points_per_unit' => '50.00',
            ],
        ])->assertCreated()->json('data');
        $versionOne = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions/{$draftOne['id']}/publish",
            ['lock_version' => $draftOne['lock_version']],
        )->assertOk()->json('data');

        $draftTwo = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => 'lot',
                'points_per_unit' => '75.00',
            ],
        ])->assertCreated()->json('data');
        $versionTwo = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions/{$draftTwo['id']}/publish",
            ['lock_version' => $draftTwo['lock_version']],
        )->assertOk()->json('data');

        $fixture = [
            'plan_id' => $plan['id'],
            'plan_lock_version' => $plan['lock_version'],
            'program_id' => $program['id'],
            'module_id' => $moduleId,
            'rule_id' => $rule['id'],
            'version_one_id' => $versionOne['id'],
            'version_two_id' => $versionTwo['id'],
        ];

        if ($includeDraft) {
            $draft = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions", [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'lot',
                    'points_per_unit' => '90.00',
                ],
            ])->assertCreated()->json('data');
            $fixture['draft_version_id'] = $draft['id'];
        }

        return $fixture;
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
