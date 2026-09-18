<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Enums\PlanProgressionPeriod;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ResolvePlanProgressionContextPortTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_it_resolves_historical_operational_state_and_current_period(): void
    {
        $t1 = CarbonImmutable::parse('2026-09-10T10:00:00.000000Z');
        $t2 = CarbonImmutable::parse('2026-09-11T10:00:00.000000Z');
        $t3 = CarbonImmutable::parse('2026-09-12T10:00:00.000000Z');

        CarbonImmutable::setTestNow($t1);
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'prog-plan',
            'name' => 'Progression plan',
            'module_ids' => [$this->brokerId()],
            'progression_period' => PlanProgressionPeriod::Weekly->value,
        ])->assertCreated()
            ->assertJsonPath('data.progression_period', 'weekly')
            ->json('data');

        $port = $this->app->make(ResolvePlanProgressionContextPort::class);

        $beforeActivation = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertSame($created['id'], $beforeActivation->plan_id);
        self::assertFalse($beforeActivation->is_active);
        self::assertSame('weekly', $beforeActivation->progression_period);

        CarbonImmutable::setTestNow($t2);
        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Ready',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->json('data');

        $duringActive = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: '2026-09-11T12:00:00.000000Z',
        ));
        self::assertTrue($duringActive->is_active);
        self::assertSame('weekly', $duringActive->progression_period);

        $historicalInactive = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertFalse($historicalInactive->is_active);

        CarbonImmutable::setTestNow($t3);
        $updated = $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$created['id']}", [
            'progression_period' => PlanProgressionPeriod::Daily->value,
            'lock_version' => $activated['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.progression_period', 'daily')
            ->json('data');

        $afterPeriodChange = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: '2026-09-12T12:00:00.000000Z',
        ));
        self::assertTrue($afterPeriodChange->is_active);
        self::assertSame('daily', $afterPeriodChange->progression_period);

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/deactivate", [
            'reason' => 'Pause',
            'lock_version' => $updated['lock_version'],
        ])->assertOk();

        $afterDeactivate = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: CarbonImmutable::now('UTC')->toISOString(),
        ));
        self::assertFalse($afterDeactivate->is_active);
        self::assertSame('daily', $afterDeactivate->progression_period);

        $stillActiveHistorically = $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $created['id'],
            occurred_at: '2026-09-11T12:00:00.000000Z',
        ));
        self::assertTrue($stillActiveHistorically->is_active);
    }

    public function test_it_requires_progression_period_on_create_and_rejects_unknown_plans(): void
    {
        $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'missing-period',
            'name' => 'Missing period',
        ])->assertUnprocessable();

        $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'bad-period',
            'name' => 'Bad period',
            'progression_period' => 'yearly',
        ])->assertUnprocessable();

        $port = $this->app->make(ResolvePlanProgressionContextPort::class);
        $this->expectException(PlanNotFoundException::class);
        $port->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: (string) Str::uuid7(),
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
