<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use App\Features\Programs\Catalog\Exceptions\DuplicateProgramCodeException;
use App\Features\Programs\Catalog\Exceptions\ProgramConcurrencyException;
use App\Features\Programs\Catalog\Exceptions\ProgramReorderConflictException;
use App\Features\Programs\Catalog\Models\Program;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class ProgramRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): ProgramRepositoryInterface;

    abstract protected function planId(): string;

    /** @return list<string> */
    abstract protected function moduleIds(): array;

    public function test_it_creates_lists_and_recovers_a_program(): void
    {
        $repository = $this->repository();
        $program = $this->program('basic', 1, $this->moduleIds());
        $repository->create($program);

        $stored = $repository->findByPlanAndId($this->planId(), $program->id);
        self::assertNotNull($stored);
        self::assertSame('basic', $stored->code);
        self::assertSame(1, $stored->position);
        self::assertSame($this->moduleIds(), $stored->moduleIds());

        $listed = $repository->listByPlanId($this->planId());
        self::assertCount(1, $listed);
        self::assertSame($program->id, $listed[0]->id);
        self::assertSame(2, $repository->nextPosition($this->planId()));
    }

    public function test_it_rejects_duplicate_codes_within_the_same_plan(): void
    {
        $repository = $this->repository();
        $repository->create($this->program('basic', 1, []));

        $this->expectException(DuplicateProgramCodeException::class);
        $repository->create($this->program('basic', 2, []));
    }

    public function test_it_replaces_selections_and_rejects_obsolete_lock_versions(): void
    {
        $repository = $this->repository();
        $program = $this->program('basic', 1, [$this->moduleIds()[0]]);
        $repository->create($program);

        $writer = $repository->findById($program->id);
        $stale = $repository->findById($program->id);
        self::assertNotNull($writer);
        self::assertNotNull($stale);

        $writer->replaceSelections($this->moduleIds(), static fn (): string => (string) Str::uuid7(), $this->now());
        $repository->update($writer, 1);

        $stored = $repository->findById($program->id);
        self::assertNotNull($stored);
        self::assertSame($this->moduleIds(), $stored->moduleIds());
        self::assertSame(2, $stored->lockVersion);

        $stale->updateAdministrativeFields('Stale', null, $this->now());
        $this->expectException(ProgramConcurrencyException::class);
        $repository->update($stale, 1);
    }

    public function test_it_reorders_programs_contiguously(): void
    {
        $repository = $this->repository();
        $first = $this->program('basic', 1, []);
        $second = $this->program('advanced', 2, []);
        $repository->create($first);
        $repository->create($second);

        $repository->reorder($this->planId(), [$second->id, $first->id], $this->now());

        $listed = $repository->listByPlanId($this->planId());
        self::assertSame(['advanced', 'basic'], array_map(
            static fn (Program $program): string => $program->code,
            $listed,
        ));
        self::assertSame([1, 2], array_map(
            static fn (Program $program): int => $program->position,
            $listed,
        ));
        self::assertSame(2, $listed[0]->lockVersion);
    }

    public function test_it_rejects_incomplete_reorder_sets(): void
    {
        $repository = $this->repository();
        $first = $this->program('basic', 1, []);
        $repository->create($first);
        $repository->create($this->program('advanced', 2, []));

        $this->expectException(ProgramReorderConflictException::class);
        $repository->reorder($this->planId(), [$first->id], $this->now());
    }

    public function test_transaction_rolls_back_after_an_error(): void
    {
        $repository = $this->repository();
        $program = $this->program('basic', 1, []);
        $repository->create($program);

        try {
            $repository->transaction(function () use ($repository, $program): void {
                $program->updateAdministrativeFields('Changed', null, $this->now());
                $repository->update($program, 1);
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $stored = $repository->findById($program->id);
        self::assertNotNull($stored);
        self::assertSame('Basic', $stored->name);
        self::assertSame(1, $stored->lockVersion);
    }

    /**
     * @param  list<string>  $moduleIds
     */
    protected function program(string $code, int $position, array $moduleIds): Program
    {
        return Program::create(
            id: (string) Str::uuid7(),
            planId: $this->planId(),
            code: $code,
            name: ucfirst($code),
            description: null,
            position: $position,
            moduleIds: $moduleIds,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
    }

    protected function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
