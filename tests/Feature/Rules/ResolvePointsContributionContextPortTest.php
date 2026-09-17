<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Exceptions\AmbiguousPointsContributionRuleException;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Rules\Services\Strategies\PointsPerQuantityUnitStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ResolvePointsContributionContextPortTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_it_resolves_historical_points_rule_context_or_absence(): void
    {
        $fixture = $this->createPointsFixture();
        $t1 = CarbonImmutable::parse('2026-09-10T10:00:00.000000Z');
        $t2 = CarbonImmutable::parse('2026-09-11T10:00:00.000000Z');

        CarbonImmutable::setTestNow($t1);
        $created = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertCreated()->json('data');

        $port = $this->app->make(ResolvePointsContributionContextPort::class);

        $beforeAssignment = $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'closed_trading_volume',
            unit_code: 'lot',
            occurred_at: '2020-01-01T00:00:00.000000Z',
        ));
        self::assertFalse($beforeAssignment->found());
        self::assertNull($beforeAssignment->context);

        $duringFirst = $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'closed_trading_volume',
            unit_code: 'lot',
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertTrue($duringFirst->found());
        self::assertNotNull($duringFirst->context);
        self::assertSame($fixture['rule_id'], $duringFirst->context->rule_id);
        self::assertSame($fixture['version_one_id'], $duringFirst->context->rule_version_id);
        self::assertSame($created['id'], $duringFirst->context->rule_assignment_id);
        self::assertSame(PointsPerQuantityUnitStrategy::TYPE, $duringFirst->context->strategy_type);
        self::assertSame('all', $duringFirst->context->scope_type);
        self::assertSame('lot', $duringFirst->context->unit);
        self::assertSame('50.00', $duringFirst->context->weight);

        $wrongUnit = $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'deposit',
            unit_code: 'usd',
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertFalse($wrongUnit->found());

        CarbonImmutable::setTestNow($t2);
        $replaced = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments/{$created['id']}/replace",
            [
                'rule_version_id' => $fixture['version_two_id'],
                'lock_version' => $created['lock_version'],
            ],
        )->assertOk()->json('data');

        $afterReplace = $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'closed_trading_volume',
            unit_code: 'lot',
            occurred_at: '2026-09-11T12:00:00.000000Z',
        ));
        self::assertTrue($afterReplace->found());
        self::assertNotNull($afterReplace->context);
        self::assertSame($fixture['version_two_id'], $afterReplace->context->rule_version_id);
        self::assertSame($replaced['id'], $afterReplace->context->rule_assignment_id);
        self::assertSame('75.00', $afterReplace->context->weight);

        $historical = $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'closed_trading_volume',
            unit_code: 'lot',
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertTrue($historical->found());
        self::assertNotNull($historical->context);
        self::assertSame($fixture['version_one_id'], $historical->context->rule_version_id);
        self::assertSame($created['id'], $historical->context->rule_assignment_id);
        self::assertSame('50.00', $historical->context->weight);

        CarbonImmutable::setTestNow();
    }

    public function test_it_allows_distinct_units_and_rejects_duplicate_unit_across_rules(): void
    {
        $fixture = $this->createPointsFixture();

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$fixture['rule_id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $fixture['version_one_id'],
            ],
        )->assertCreated();

        $usdRule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules", [
            'name' => 'Deposit Points',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');
        $usdDraft = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$usdRule['id']}/versions",
            [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'usd',
                    'points_per_unit' => '1.00',
                ],
            ],
        )->assertCreated()->json('data');
        $usdVersion = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$usdRule['id']}/versions/{$usdDraft['id']}/publish",
            ['lock_version' => $usdDraft['lock_version']],
        )->assertOk()->json('data');

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$usdRule['id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $usdVersion['id'],
            ],
        )->assertCreated();

        $duplicateLotRule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules", [
            'name' => 'Lot Points Duplicate',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');
        $duplicateDraft = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$duplicateLotRule['id']}/versions",
            [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'lot',
                    'points_per_unit' => '10.00',
                ],
            ],
        )->assertCreated()->json('data');
        $duplicateVersion = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$duplicateLotRule['id']}/versions/{$duplicateDraft['id']}/publish",
            ['lock_version' => $duplicateDraft['lock_version']],
        )->assertOk()->json('data');

        $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$duplicateLotRule['id']}/assignments",
            [
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'rule_version_id' => $duplicateVersion['id'],
            ],
        )->assertConflict()->assertJsonPath('error.code', 'POINTS_PER_QUANTITY_UNIT_ASSIGNMENT_CONFLICT');
    }

    public function test_it_rejects_ambiguous_historical_matches(): void
    {
        $fixture = $this->createPointsFixture();
        $now = now('UTC');

        $secondRule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules", [
            'name' => 'Ambiguous Lot',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');
        $draft = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$secondRule['id']}/versions",
            [
                'schema_version' => 1,
                'configuration' => [
                    'unit' => 'lot',
                    'points_per_unit' => '9.00',
                ],
            ],
        )->assertCreated()->json('data');
        $version = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$fixture['plan_id']}/rules/{$secondRule['id']}/versions/{$draft['id']}/publish",
            ['lock_version' => $draft['lock_version']],
        )->assertOk()->json('data');

        $startsAt = $now->copy()->subHour()->utc()->toISOString();
        DB::table('rule_assignments')->insert([
            [
                'id' => (string) Str::uuid7(),
                'rule_id' => $fixture['rule_id'],
                'rule_version_id' => $fixture['version_one_id'],
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'scope_type' => 'all',
                'starts_at' => $startsAt,
                'ends_at' => null,
                'lock_version' => 1,
                'created_at' => $startsAt,
                'updated_at' => $startsAt,
            ],
            [
                'id' => (string) Str::uuid7(),
                'rule_id' => $secondRule['id'],
                'rule_version_id' => $version['id'],
                'program_id' => $fixture['program_id'],
                'module_id' => $fixture['module_id'],
                'scope_type' => 'all',
                'starts_at' => $startsAt,
                'ends_at' => null,
                'lock_version' => 1,
                'created_at' => $startsAt,
                'updated_at' => $startsAt,
            ],
        ]);

        $port = $this->app->make(ResolvePointsContributionContextPort::class);

        $this->expectException(AmbiguousPointsContributionRuleException::class);
        $port->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $fixture['program_id'],
            module_id: $fixture['module_id'],
            metric_code: 'closed_trading_volume',
            unit_code: 'lot',
            occurred_at: $now->utc()->toISOString(),
        ));
    }

    /**
     * @return array{
     *     plan_id: string,
     *     program_id: string,
     *     module_id: string,
     *     rule_id: string,
     *     version_one_id: string,
     *     version_two_id: string
     * }
     */
    private function createPointsFixture(): array
    {
        $moduleId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'points-plan',
            'name' => 'Points Plan',
            'module_ids' => [$moduleId],
        ])->assertCreated()->json('data');

        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'entry_threshold' => 0,
            'module_ids' => [$moduleId],
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/rules", [
            'name' => 'Lot Points',
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

        return [
            'plan_id' => $plan['id'],
            'program_id' => $program['id'],
            'module_id' => $moduleId,
            'rule_id' => $rule['id'],
            'version_one_id' => $versionOne['id'],
            'version_two_id' => $versionTwo['id'],
        ];
    }
}
