<?php

declare(strict_types=1);

namespace Tests\Unit\Plans\Catalog;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Models\Plan;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryPlanRepository;
use App\Features\Plans\Catalog\UseCases\ResolvePlanSubscriptionContextUseCase;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResolvePlanSubscriptionContextUseCaseTest extends TestCase
{
    public function test_it_resolves_active_plan_subscription_context(): void
    {
        $repository = new InMemoryPlanRepository;
        $this->app->instance('plans.repositories.memory', $repository);
        config()->set('plans.repository', 'memory');

        $now = CarbonImmutable::now('UTC')->toISOString();
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: 'mix',
            name: 'Mix',
            description: null,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
            requiresApproval: false,
        );
        $plan->isActive = true;
        $repository->create($plan);

        $context = (new ResolvePlanSubscriptionContextUseCase(app(PlanRepositoryFactory::class)))
            ->resolve(new ResolvePlanSubscriptionContextQueryData(plan_id: $plan->id));

        self::assertSame($plan->id, $context->id);
        self::assertTrue($context->is_active);
        self::assertFalse($context->archived);
        self::assertFalse($context->requires_approval);
    }

    public function test_it_resolves_inactive_and_archived_plans(): void
    {
        $repository = new InMemoryPlanRepository;
        $this->app->instance('plans.repositories.memory', $repository);
        config()->set('plans.repository', 'memory');

        $now = CarbonImmutable::now('UTC')->toISOString();
        $inactive = Plan::create(
            id: (string) Str::uuid7(),
            code: 'inactive',
            name: 'Inactive',
            description: null,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $archived = Plan::create(
            id: (string) Str::uuid7(),
            code: 'archived',
            name: 'Archived',
            description: null,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
            requiresApproval: true,
        );
        $archived->archive($now);
        $repository->create($inactive);
        $repository->create($archived);

        $useCase = new ResolvePlanSubscriptionContextUseCase(app(PlanRepositoryFactory::class));

        $inactiveContext = $useCase->resolve(new ResolvePlanSubscriptionContextQueryData(plan_id: $inactive->id));
        self::assertFalse($inactiveContext->is_active);
        self::assertFalse($inactiveContext->archived);
        self::assertTrue($inactiveContext->requires_approval);

        $archivedContext = $useCase->resolve(new ResolvePlanSubscriptionContextQueryData(plan_id: $archived->id));
        self::assertFalse($archivedContext->is_active);
        self::assertTrue($archivedContext->archived);
        self::assertTrue($archivedContext->requires_approval);
    }

    public function test_it_rejects_unknown_plans(): void
    {
        $repository = new InMemoryPlanRepository;
        $this->app->instance('plans.repositories.memory', $repository);
        config()->set('plans.repository', 'memory');

        $this->expectException(PlanNotFoundException::class);
        (new ResolvePlanSubscriptionContextUseCase(app(PlanRepositoryFactory::class)))
            ->resolve(new ResolvePlanSubscriptionContextQueryData(plan_id: (string) Str::uuid7()));
    }
}
