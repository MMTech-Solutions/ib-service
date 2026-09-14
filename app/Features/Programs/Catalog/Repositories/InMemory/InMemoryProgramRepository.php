<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\InMemory;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use App\Features\Programs\Catalog\Exceptions\DuplicateProgramCodeException;
use App\Features\Programs\Catalog\Exceptions\ProgramConcurrencyException;
use App\Features\Programs\Catalog\Exceptions\ProgramReorderConflictException;
use App\Features\Programs\Catalog\Models\Program;
use Closure;
use Throwable;

final class InMemoryProgramRepository implements ProgramRepositoryInterface
{
    /** @var array<string, Program> */
    private array $programs = [];

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->programs), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->programs = $snapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?Program
    {
        $program = $this->programs[$id] ?? null;

        return $program === null ? null : $this->copy($program);
    }

    public function findByPlanAndId(string $planId, string $programId): ?Program
    {
        $program = $this->findById($programId);
        if ($program === null || $program->planId !== $planId) {
            return null;
        }

        return $program;
    }

    public function listByPlanId(string $planId): array
    {
        $programs = array_values(array_filter(
            $this->programs,
            static fn (Program $program): bool => $program->planId === $planId,
        ));
        usort($programs, static fn (Program $a, Program $b): int => $a->position <=> $b->position);

        return array_map(fn (Program $program): Program => $this->copy($program), $programs);
    }

    public function nextPosition(string $planId): int
    {
        $max = 0;
        foreach ($this->programs as $program) {
            if ($program->planId === $planId && $program->position > $max) {
                $max = $program->position;
            }
        }

        return $max + 1;
    }

    public function create(Program $program): void
    {
        foreach ($this->programs as $stored) {
            if ($stored->planId === $program->planId && $stored->code === $program->code) {
                throw DuplicateProgramCodeException::forCode($program->planId, $program->code);
            }
        }

        $this->programs[$program->id] = $this->copy($program);
    }

    public function update(Program $program, int $expectedLockVersion): void
    {
        $stored = $this->programs[$program->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw ProgramConcurrencyException::forProgram($program->id);
        }

        $program->lockVersion = $expectedLockVersion + 1;
        $this->programs[$program->id] = $this->copy($program);
    }

    public function reorder(string $planId, array $orderedProgramIds, string $now): void
    {
        $current = $this->listByPlanId($planId);
        $currentIds = array_map(static fn (Program $program): string => $program->id, $current);
        $incoming = array_values($orderedProgramIds);
        $sortedCurrent = $currentIds;
        $sortedIncoming = $incoming;
        sort($sortedCurrent);
        sort($sortedIncoming);

        if ($sortedCurrent !== $sortedIncoming || count($incoming) !== count(array_unique($incoming))) {
            throw ProgramReorderConflictException::forPlan($planId);
        }

        foreach ($incoming as $index => $programId) {
            $program = $this->programs[$programId] ?? null;
            if ($program === null || $program->planId !== $planId) {
                throw ProgramReorderConflictException::forPlan($planId);
            }

            $program->position = $index + 1;
            $program->updatedAt = $now;
            $program->lockVersion++;
            $this->programs[$programId] = $this->copy($program);
        }
    }

    private function copy(Program $program): Program
    {
        /** @var Program $copy */
        $copy = unserialize(serialize($program), ['allowed_classes' => true]);

        return $copy;
    }
}
