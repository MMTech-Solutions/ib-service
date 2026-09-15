<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class RuleCatalogEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_manage_rules_and_versions_under_a_plan(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $created = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA Standard',
            'description' => 'Reusable CPA',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'CPA Standard')
            ->assertJsonPath('data.slug', 'cpa-standard')
            ->assertJsonPath('data.strategy_type', RuleStrategyType::PointsPerQuantityUnit->value)
            ->assertJsonPath('data.versions', [])
            ->json('data');

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/rules")
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'cpa-standard');

        $draft = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => 'lot',
                'points_per_unit' => '50.00',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        $updatedDraft = $this->gatewayJson(
            'PATCH',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions/{$draft['id']}",
            [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'lot',
                    'points_per_unit' => '60.00',
                ],
                'lock_version' => $draft['lock_version'],
            ],
        )->assertOk()
            ->assertJsonPath('data.configuration.points_per_unit', '60.00')
            ->json('data');

        $published = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions/{$updatedDraft['id']}/publish",
            ['lock_version' => $updatedDraft['lock_version']],
        )->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->json('data');

        $second = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => 'lot',
                'points_per_unit' => '75.00',
            ],
        ])->assertCreated()->json('data');

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions/{$second['id']}/publish",
            ['lock_version' => $second['lock_version']],
        )->assertOk();

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.versions.0.status', 'published')
            ->assertJsonPath('data.versions.1.status', 'published')
            ->assertJsonMissingPath('data.current_version_id')
            ->assertJsonMissingPath('data.active_version_id');

        $this->gatewayJson(
            'PATCH',
            "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}/versions/{$published['id']}",
            [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'lot',
                    'points_per_unit' => '90.00',
                ],
                'lock_version' => $published['lock_version'],
            ],
        )->assertUnprocessable()->assertJsonPath('error.code', 'RULE_VERSION_IMMUTABLE');

        $renamed = $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$created['id']}", [
            'name' => 'Volume CPA',
            'lock_version' => $created['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Volume CPA')
            ->assertJsonPath('data.slug', 'cpa-standard')
            ->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'Volume CPA',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertConflict()->assertJsonPath('error.code', 'RULE_NAME_CONFLICT');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA  Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertConflict()->assertJsonPath('error.code', 'RULE_SLUG_CONFLICT');

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$renamed['id']}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'cpa-standard');
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => 'lot',
                'points_per_unit' => '0',
            ],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'RULE_CONFIGURATION_INVALID');
    }

    public function test_archived_plans_reject_rule_mutations(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan['id']}", [
            'reason' => 'Retired',
            'lock_version' => $plan['lock_version'],
        ])->assertNoContent();

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'Volume',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_ARCHIVED');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}", [
            'name' => 'Renamed',
            'lock_version' => $rule['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_ARCHIVED');

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}/rules")
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'cpa-standard');
    }

    public function test_rule_routes_enforce_gateway_identity_and_permission(): void
    {
        $planId = '01993ac2-8750-73fd-b102-ba24fb06d8be';
        $this->getJson("/api/ib/v1/admin/plans/{$planId}/rules")->assertUnauthorized();
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/plans/{$planId}/rules", [
            'name' => 'CPA Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ]);
    }

    public function test_rule_concurrency_conflict_is_reported(): void
    {
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'CPA Standard',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}", [
            'name' => 'First writer',
            'lock_version' => $rule['lock_version'],
        ])->assertOk();

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$plan['id']}/rules/{$rule['id']}", [
            'name' => 'Stale writer',
            'lock_version' => $rule['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'RULE_CONCURRENCY_CONFLICT');
    }
}
