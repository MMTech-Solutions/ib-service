<?php

declare(strict_types=1);

namespace Tests\Unit\Programs\Catalog;

use App\Features\Programs\Catalog\Factories\ProgramRepositoryFactory;
use App\Features\Programs\Catalog\Models\Program;
use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use App\Features\Programs\Catalog\UseCases\ResolveProgramContextUseCase;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramContextQueryData;
use App\Features\Programs\Contracts\Exceptions\ModuleNotSelectedOnProgramException;
use App\Features\Programs\Contracts\Exceptions\ProgramNotFoundException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResolveProgramContextUseCaseTest extends TestCase
{
    public function test_it_resolves_program_context_and_selected_modules(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $planId = (string) Str::uuid7();
        $moduleId = (string) Str::uuid7();
        $now = CarbonImmutable::now('UTC')->toISOString();
        $program = Program::create(
            id: (string) Str::uuid7(),
            planId: $planId,
            code: 'basic',
            name: 'Basic',
            description: null,
            position: 1,
            entryThreshold: 0,
            moduleIds: [$moduleId],
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $repository->create($program);

        $useCase = new ResolveProgramContextUseCase(app(ProgramRepositoryFactory::class));
        $context = $useCase->assertSelectedModule(new AssertSelectedModuleQueryData(
            plan_id: $planId,
            program_id: $program->id,
            module_id: $moduleId,
        ));

        self::assertSame($program->id, $context->id);
        self::assertSame($planId, $context->plan_id);
        self::assertSame([$moduleId], $context->selected_module_ids);
    }

    public function test_it_rejects_unknown_program(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $useCase = new ResolveProgramContextUseCase(app(ProgramRepositoryFactory::class));
        $this->expectException(ProgramNotFoundException::class);
        $useCase->resolve(new ResolveProgramContextQueryData(
            plan_id: (string) Str::uuid7(),
            program_id: (string) Str::uuid7(),
        ));
    }

    public function test_it_rejects_unselected_module(): void
    {
        $repository = new InMemoryProgramRepository;
        $this->app->instance('programs.repositories.memory', $repository);
        config()->set('programs.repository', 'memory');

        $planId = (string) Str::uuid7();
        $moduleId = (string) Str::uuid7();
        $now = CarbonImmutable::now('UTC')->toISOString();
        $program = Program::create(
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
        $repository->create($program);

        $useCase = new ResolveProgramContextUseCase(app(ProgramRepositoryFactory::class));
        $this->expectException(ModuleNotSelectedOnProgramException::class);
        $useCase->assertSelectedModule(new AssertSelectedModuleQueryData(
            plan_id: $planId,
            program_id: $program->id,
            module_id: $moduleId,
        ));
    }
}
