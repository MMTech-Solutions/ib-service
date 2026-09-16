<?php

declare(strict_types=1);

namespace Tests\Unit\Programs\Catalog;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Models\Program;
use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use App\Features\Programs\Catalog\UseCases\ResolveProgramSubscriptionContextUseCase;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveFirstProgramByPositionQueryData;
use App\Features\Programs\Contracts\Exceptions\ProgramNotAvailableForPlanException;
use App\Features\Programs\Contracts\Exceptions\ProgramNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResolveProgramSubscriptionContextUseCaseTest extends TestCase
{
    public function test_it_asserts_a_program_belongs_to_the_plan(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $planId = (string) Str::uuid7();
        $now = CarbonImmutable::now('UTC')->toISOString();
        $program = Program::create(
            id: (string) Str::uuid7(),
            planId: $planId,
            code: 'basic',
            name: 'Basic',
            description: null,
            position: 2,
            entryThreshold: 1,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $repository->create($program);

        $context = (new ResolveProgramSubscriptionContextUseCase(app(ProgramRepositoryFactory::class)))
            ->assertBelongsToPlan(new AssertProgramBelongsToPlanQueryData(
                plan_id: $planId,
                program_id: $program->id,
            ));

        self::assertSame($program->id, $context->id);
        self::assertSame($planId, $context->plan_id);
        self::assertSame(2, $context->position);
    }

    public function test_it_rejects_a_program_from_another_plan(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $ownerPlanId = (string) Str::uuid7();
        $otherPlanId = (string) Str::uuid7();
        $now = CarbonImmutable::now('UTC')->toISOString();
        $program = Program::create(
            id: (string) Str::uuid7(),
            planId: $ownerPlanId,
            code: 'basic',
            name: 'Basic',
            description: null,
            position: 1,
            entryThreshold: 0,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $repository->create($program);

        $this->expectException(ProgramNotFoundException::class);
        (new ResolveProgramSubscriptionContextUseCase(app(ProgramRepositoryFactory::class)))
            ->assertBelongsToPlan(new AssertProgramBelongsToPlanQueryData(
                plan_id: $otherPlanId,
                program_id: $program->id,
            ));
    }

    public function test_it_resolves_the_first_program_by_position_deterministically(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $planId = (string) Str::uuid7();
        $now = CarbonImmutable::now('UTC')->toISOString();
        $second = Program::create(
            id: (string) Str::uuid7(),
            planId: $planId,
            code: 'advanced',
            name: 'Advanced',
            description: null,
            position: 2,
            entryThreshold: 10,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $first = Program::create(
            id: (string) Str::uuid7(),
            planId: $planId,
            code: 'basic',
            name: 'Basic',
            description: null,
            position: 1,
            entryThreshold: 0,
            moduleIds: [],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $repository->create($second);
        $repository->create($first);

        $context = (new ResolveProgramSubscriptionContextUseCase(app(ProgramRepositoryFactory::class)))
            ->resolveFirstByPosition(new ResolveFirstProgramByPositionQueryData(plan_id: $planId));

        self::assertSame($first->id, $context->id);
        self::assertSame(1, $context->position);
    }

    public function test_it_rejects_an_empty_ladder(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $this->expectException(ProgramNotAvailableForPlanException::class);
        (new ResolveProgramSubscriptionContextUseCase(app(ProgramRepositoryFactory::class)))
            ->resolveFirstByPosition(new ResolveFirstProgramByPositionQueryData(
                plan_id: (string) Str::uuid7(),
            ));
    }
}
