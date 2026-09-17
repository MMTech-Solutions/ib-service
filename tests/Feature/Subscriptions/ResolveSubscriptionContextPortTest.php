<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class ResolveSubscriptionContextPortTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private string $customerSub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->customerSub = $this->seedAuthorizedCustomer();
    }

    public function test_it_resolves_historical_subscription_context_or_absence(): void
    {
        $t1 = CarbonImmutable::parse('2026-09-10T10:00:00.000000Z');
        $t2 = CarbonImmutable::parse('2026-09-11T10:00:00.000000Z');
        $t3 = CarbonImmutable::parse('2026-09-12T10:00:00.000000Z');

        CarbonImmutable::setTestNow($t1);
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'ctx-plan');
        $secondProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'entry_threshold' => 100,
        ])->assertCreated()->json('data');

        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $port = $this->app->make(ResolveSubscriptionContextPort::class);

        $beforeActivation = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2020-01-01T00:00:00.000000Z',
        ));
        self::assertFalse($beforeActivation->found());
        self::assertNull($beforeActivation->context);

        $duringFirst = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertTrue($duringFirst->found());
        self::assertNotNull($duringFirst->context);
        self::assertSame($active['id'], $duringFirst->context->subscription_id);
        self::assertSame($plan['id'], $duringFirst->context->plan_id);
        self::assertSame($plan['program_id'], $duringFirst->context->program_id);
        self::assertSame($active['current_placement']['id'], $duringFirst->context->placement_id);
        self::assertSame(PlacementCondition::Unfixed->value, $duringFirst->context->placement_condition);

        CarbonImmutable::setTestNow($t2);
        $changed = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/change",
            [
                'program_id' => $secondProgram['id'],
                'lock_version' => $active['lock_version'],
            ],
        )->assertOk()->json('data');

        $afterChange = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-11T12:00:00.000000Z',
        ));
        self::assertTrue($afterChange->found());
        self::assertNotNull($afterChange->context);
        self::assertSame($secondProgram['id'], $afterChange->context->program_id);
        self::assertSame($changed['current_placement']['id'], $afterChange->context->placement_id);
        self::assertSame(PlacementCondition::Unfixed->value, $afterChange->context->placement_condition);

        $historical = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));
        self::assertTrue($historical->found());
        self::assertNotNull($historical->context);
        self::assertSame($plan['program_id'], $historical->context->program_id);
        self::assertSame($active['current_placement']['id'], $historical->context->placement_id);
        self::assertSame(PlacementCondition::Unfixed->value, $historical->context->placement_condition);

        $atBoundary = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: $t2->toISOString(),
        ));
        self::assertTrue($atBoundary->found());
        self::assertNotNull($atBoundary->context);
        self::assertSame($secondProgram['id'], $atBoundary->context->program_id);

        CarbonImmutable::setTestNow($t3);
        $fixed = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/subscriptions/{$changed['id']}/placement/fix",
            [
                'program_id' => $secondProgram['id'],
                'lock_version' => $changed['lock_version'],
                'reason' => 'Hold',
            ],
        )->assertOk()->json('data');

        $whileFixed = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-12T10:30:00.000000Z',
        ));
        self::assertTrue($whileFixed->found());
        self::assertNotNull($whileFixed->context);
        self::assertSame($fixed['current_placement']['id'], $whileFixed->context->placement_id);
        self::assertSame(PlacementCondition::Fixed->value, $whileFixed->context->placement_condition);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-12T14:00:00.000000Z'));
        $cancelled = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/subscriptions/{$fixed['id']}/cancel",
            [
                'lock_version' => $fixed['lock_version'],
                'reason' => 'Exit',
            ],
        )->assertOk()->json('data');

        $afterCancelHistorical = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-12T10:30:00.000000Z',
        ));
        self::assertTrue($afterCancelHistorical->found());
        self::assertNotNull($afterCancelHistorical->context);
        self::assertSame($fixed['current_placement']['id'], $afterCancelHistorical->context->placement_id);
        self::assertSame(PlacementCondition::Fixed->value, $afterCancelHistorical->context->placement_condition);

        $afterClosed = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: CarbonImmutable::parse($cancelled['closed_at'])->utc()->addSecond()->toISOString(),
        ));
        self::assertFalse($afterClosed->found());
    }

    public function test_it_returns_absence_for_pending_subscription(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T10:00:00.000000Z'));
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'pending-ctx');
        $pending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $port = $this->app->make(ResolveSubscriptionContextPort::class);
        $result = $port->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $this->customerSub,
            occurred_at: '2026-09-10T12:00:00.000000Z',
        ));

        self::assertSame('pending', $pending['status']);
        self::assertFalse($result->found());
    }

    /**
     * @return array{id: string, program_id: ?string}
     */
    private function createActivePlan(bool $requiresApproval, bool $withProgram, string $code = 'mix'): array
    {
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => $code,
            'name' => ucfirst($code),
            'module_ids' => [$this->brokerId()],
            'requires_approval' => $requiresApproval,
        ])->assertCreated()->json('data');

        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Ready',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->json('data');

        $programId = null;
        if ($withProgram) {
            $programId = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/programs", [
                'code' => 'basic',
                'name' => 'Basic',
                'entry_threshold' => 0,
            ])->assertCreated()->json('data.id');
        }

        return [
            'id' => $activated['id'],
            'program_id' => $programId,
        ];
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
