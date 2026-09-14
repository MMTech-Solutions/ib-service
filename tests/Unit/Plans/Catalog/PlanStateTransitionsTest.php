<?php

declare(strict_types=1);

namespace Tests\Unit\Plans\Catalog;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Plans\Catalog\Exceptions\PlanCannotActivateException;
use App\Features\Plans\Catalog\Exceptions\PlanCannotArchiveWhenActiveException;
use App\Features\Plans\Catalog\Exceptions\PlanCannotClearBindingsWhenActiveException;
use App\Features\Plans\Catalog\Models\Plan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlanStateTransitionsTest extends TestCase
{
    public function test_a_new_plan_is_inactive_and_may_have_no_modules(): void
    {
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: 'mix',
            name: 'Mix',
            description: null,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-09-14T00:00:00.000000Z',
        );

        self::assertFalse($plan->isActive);
        self::assertSame([], $plan->moduleIds());
        $this->expectException(PlanCannotActivateException::class);
        $plan->activate([], '2026-09-14T00:00:01.000000Z');
    }

    public function test_activation_requires_an_operational_bound_module(): void
    {
        $moduleId = (string) Str::uuid7();
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: 'broker-plan',
            name: 'Broker',
            description: null,
            moduleIds: [$moduleId],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-09-14T00:00:00.000000Z',
        );

        $this->expectException(PlanCannotActivateException::class);
        $plan->activate([
            new ModuleSummaryData($moduleId, 'broker', 'Broker', false, 'running'),
        ], '2026-09-14T00:00:01.000000Z');
    }

    public function test_a_paused_module_remains_operational(): void
    {
        $moduleId = (string) Str::uuid7();
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: 'broker-plan',
            name: 'Broker',
            description: null,
            moduleIds: [$moduleId],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-09-14T00:00:00.000000Z',
        );

        self::assertTrue($plan->activate([
            new ModuleSummaryData($moduleId, 'broker', 'Broker', true, 'paused'),
        ], '2026-09-14T00:00:01.000000Z'));
        self::assertTrue($plan->isActive);
        self::assertFalse($plan->activate([
            new ModuleSummaryData($moduleId, 'broker', 'Broker', true, 'paused'),
        ], '2026-09-14T00:00:02.000000Z'));
    }

    public function test_an_active_plan_cannot_clear_bindings_or_be_archived(): void
    {
        $moduleId = (string) Str::uuid7();
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: 'broker-plan',
            name: 'Broker',
            description: null,
            moduleIds: [$moduleId],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-09-14T00:00:00.000000Z',
        );
        $plan->activate([
            new ModuleSummaryData($moduleId, 'broker', 'Broker', true, 'running'),
        ], '2026-09-14T00:00:01.000000Z');

        try {
            $plan->replaceBindings([], static fn (): string => (string) Str::uuid7(), '2026-09-14T00:00:02.000000Z');
            self::fail('Active plans must keep at least one binding.');
        } catch (PlanCannotClearBindingsWhenActiveException) {
        }

        try {
            $plan->archive('2026-09-14T00:00:03.000000Z');
            self::fail('Active plans cannot be archived.');
        } catch (PlanCannotArchiveWhenActiveException) {
        }

        self::assertTrue($plan->deactivate('2026-09-14T00:00:04.000000Z'));
        self::assertTrue($plan->archive('2026-09-14T00:00:05.000000Z'));
        self::assertNotNull($plan->deletedAt);
        self::assertFalse($plan->archive('2026-09-14T00:00:06.000000Z'));
    }
}
