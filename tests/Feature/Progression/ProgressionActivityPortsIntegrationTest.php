<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Repositories\InMemory\InMemoryModuleRepository;
use App\Features\Modules\Catalog\Repositories\NoModuleReferences;
use App\Features\Modules\Catalog\Services\ModuleActivityRejectionEvidence;
use App\Features\Modules\Catalog\Support\ProgressionActivityCursor;
use App\Features\Modules\Sources\Broker\Services\Adapters\BrokerClosedTradingVolumeActivityAdapter;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProgressionActivityPortsIntegrationTest extends TestCase
{
    private InMemoryModuleRepository $modules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modules = new InMemoryModuleRepository(new NoModuleReferences);
        $this->app->instance('modules.repositories.memory', $this->modules);
        config()->set('modules.repository', 'memory');
        Http::fake(fn () => Http::response([
            'data' => [
                ['source_activity_id' => 'broker:position:00000000-0000-7000-8000-000000000001', 'subject_external_user_id' => '11111111-1111-4111-8111-111111111111', 'metric_code' => 'closed_trading_volume', 'unit_code' => 'lot', 'quantity' => '1.5', 'occurred_at' => '2026-09-10T10:00:00+00:00', 'symbol_id' => '00000000-0000-7000-8000-000000000005', 'server_group_id' => '00000000-0000-7000-8000-000000000003'],
            ],
            'meta' => ['next_cursor' => null],
        ]));
    }

    public function test_it_delivers_paginated_normalized_activity_for_running_module(): void
    {
        $module = $this->seedBrokerModule();

        $port = $this->app->make(FetchProgressionActivitiesPort::class);
        $page = $port->fetch(new FetchProgressionActivitiesQueryData(
            module_id: $module->id,
            occurred_from: '2026-09-10T00:00:00+00:00',
            occurred_until: '2026-09-12T00:00:00+00:00',
            limit: 2,
        ));

        self::assertSame('running', $page->module_condition);
        self::assertTrue($page->provider_invoked);
        self::assertFalse($page->isRejected());
        self::assertCount(1, $page->activities);
        self::assertSame('broker:position:00000000-0000-7000-8000-000000000001', $page->activities[0]->source_activity_id);
        self::assertSame('closed_trading_volume', $page->activities[0]->metric_code);
        self::assertSame('lot', $page->activities[0]->unit_code);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005', $page->activities[0]->instrument_reference);
    }

    public function test_it_queries_activity_while_module_is_paused_without_granting_evaluation(): void
    {
        $module = $this->seedBrokerModule();
        $module->pause(CarbonImmutable::now('UTC')->toISOString());
        $this->modules->update($module, 1);

        $page = $this->app->make(FetchProgressionActivitiesPort::class)->fetch(
            new FetchProgressionActivitiesQueryData(
                module_id: $module->id,
                occurred_from: '2026-09-10T00:00:00+00:00',
                occurred_until: '2026-09-12T00:00:00+00:00',
            ),
        );

        self::assertSame('paused', $page->module_condition);
        self::assertTrue($page->provider_invoked);
        self::assertFalse($page->isRejected());
        self::assertGreaterThanOrEqual(1, count($page->activities));
    }

    public function test_inactive_module_rejects_without_invoking_provider_and_records_evidence(): void
    {
        $module = $this->seedBrokerModule();
        $module->deactivate(CarbonImmutable::now('UTC')->toISOString());
        $this->modules->update($module, 1);

        $calls = 0;
        $this->app->bind(
            BrokerClosedTradingVolumeActivityAdapter::class,
            function () use (&$calls): BrokerClosedTradingVolumeActivityAdapter {
                $calls++;

                return $this->app->make(BrokerClosedTradingVolumeActivityAdapter::class);
            },
        );

        $evidence = $this->app->make(ModuleActivityRejectionEvidence::class);

        $page = $this->app->make(FetchProgressionActivitiesPort::class)->fetch(
            new FetchProgressionActivitiesQueryData(
                module_id: $module->id,
                occurred_from: '2026-09-10T00:00:00+00:00',
                occurred_until: '2026-09-12T00:00:00+00:00',
            ),
        );

        self::assertSame('inactive', $page->module_condition);
        self::assertFalse($page->provider_invoked);
        self::assertTrue($page->isRejected());
        self::assertSame('module_inactive', $page->rejection_code);
        self::assertSame([], $page->activities);
        self::assertSame(0, $calls);
        self::assertCount(1, $evidence->recorded());
        self::assertSame($module->id, $evidence->recorded()[0]['module_id']);
    }

    public function test_progression_consumes_only_published_inter_feature_contracts(): void
    {
        $gateways = $this->app->make(ProgressionInterFeatureGateways::class);

        self::assertInstanceOf(FetchProgressionActivitiesPort::class, $gateways->activities());
        self::assertInstanceOf(ResolvePlanContextPort::class, $gateways->planContext());
        self::assertInstanceOf(ResolvePlanSubscriptionContextPort::class, $gateways->planSubscriptionContext());
        self::assertInstanceOf(ResolvePlanProgressionContextPort::class, $gateways->planProgressionContext());
        self::assertInstanceOf(ResolveProgramContextPort::class, $gateways->programContext());
        self::assertInstanceOf(ResolveProgramSubscriptionContextPort::class, $gateways->programSubscriptionContext());
        self::assertInstanceOf(HasOpenSubscriptionsForPlanPort::class, $gateways->openSubscriptionsForPlan());
        self::assertInstanceOf(ResolveSubscriptionContextPort::class, $gateways->subscriptionContext());
        self::assertInstanceOf(ResolvePointsContributionContextPort::class, $gateways->pointsContributionContext());
        self::assertTrue(
            is_file(app_path('Features/Rules/Contracts/Ports/Input/ResolvePointsContributionContextPort.php')),
            'P0.1 publishes ResolvePointsContributionContextPort in Rules.',
        );
    }

    public function test_activity_cursor_is_stable_and_opaque(): void
    {
        $encoded = ProgressionActivityCursor::encode('2026-09-10T10:00:00+00:00', 'broker-deposit-001');
        [$occurredAt, $sourceId] = ProgressionActivityCursor::decode($encoded);

        self::assertSame('2026-09-10T10:00:00+00:00', $occurredAt);
        self::assertSame('broker-deposit-001', $sourceId);
        self::assertStringNotContainsString('broker-deposit-001', $encoded);
    }

    private function seedBrokerModule(): Module
    {
        $module = Module::fromDefinition(
            id: (string) Str::uuid7(),
            definition: new ModuleDefinitionData(
                code: 'broker',
                name: 'Broker',
                description: null,
                capabilities: [
                    new ModuleCapabilityDefinitionData('deposits', 'Deposits', null),
                    new ModuleCapabilityDefinitionData('closed_trading_volume', 'Closed trading volume', null),
                ],
            ),
            generateId: static fn (): string => (string) Str::uuid7(),
            now: CarbonImmutable::now('UTC')->toISOString(),
        );
        $this->modules->create($module);

        return $module;
    }
}
