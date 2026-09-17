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
use App\Features\Modules\Sources\Broker\Services\Adapters\FixtureBrokerDepositsActivityAdapter;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use Carbon\CarbonImmutable;
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
        self::assertCount(2, $page->activities);
        self::assertSame('broker-deposit-001', $page->activities[0]->source_activity_id);
        self::assertSame('confirmed_deposit', $page->activities[0]->metric_code);
        self::assertSame('USD', $page->activities[0]->unit_code);
        self::assertNotNull($page->next_cursor);

        $second = $port->fetch(new FetchProgressionActivitiesQueryData(
            module_id: $module->id,
            occurred_from: '2026-09-10T00:00:00+00:00',
            occurred_until: '2026-09-12T00:00:00+00:00',
            cursor: $page->next_cursor,
            limit: 2,
        ));

        self::assertCount(1, $second->activities);
        self::assertSame('broker-deposit-003', $second->activities[0]->source_activity_id);
        self::assertNull($second->next_cursor);
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
            FixtureBrokerDepositsActivityAdapter::class,
            function () use (&$calls): FixtureBrokerDepositsActivityAdapter {
                $calls++;

                return new FixtureBrokerDepositsActivityAdapter;
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
        self::assertInstanceOf(ResolveProgramContextPort::class, $gateways->programContext());
        self::assertInstanceOf(ResolveProgramSubscriptionContextPort::class, $gateways->programSubscriptionContext());
        self::assertInstanceOf(HasOpenSubscriptionsForPlanPort::class, $gateways->openSubscriptionsForPlan());
        self::assertFalse(
            is_dir(app_path('Features/Rules/Contracts/Ports/Input')),
            'Rules has no Progression read port yet; Session 1 must not invent one.',
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
