<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramRepositoryInterface;
use App\Features\Programs\Catalog\Exceptions\DuplicateProgramCodeException;
use App\Features\Programs\Catalog\Exceptions\ProgramConcurrencyException;
use App\Features\Programs\Catalog\Exceptions\ProgramReorderConflictException;
use App\Features\Programs\Catalog\Models\Program;
use App\Features\Programs\Catalog\Models\ProgramModuleSelection;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlProgramRepository implements ProgramRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?Program
    {
        $record = ProgramRecord::query()->with('selections')->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByPlanAndId(string $planId, string $programId): ?Program
    {
        $record = ProgramRecord::query()
            ->with('selections')
            ->whereKey($programId)
            ->where('plan_id', $planId)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function listByPlanId(string $planId): array
    {
        return ProgramRecord::query()
            ->with('selections')
            ->where('plan_id', $planId)
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramRecord $record): Program => $this->hydrate($record))
            ->all();
    }

    public function nextPosition(string $planId): int
    {
        $max = ProgramRecord::query()->where('plan_id', $planId)->max('position');

        return ((int) $max) + 1;
    }

    public function create(Program $program): void
    {
        try {
            $this->connection->transaction(function () use ($program): void {
                ProgramRecord::query()->create($this->programAttributes($program));
                $this->syncSelections($program);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateProgramCodeException::forCode($program->planId, $program->code);
        }
    }

    public function update(Program $program, int $expectedLockVersion): void
    {
        $nextLockVersion = $expectedLockVersion + 1;
        $affected = ProgramRecord::query()
            ->whereKey($program->id)
            ->where('lock_version', $expectedLockVersion)
            ->update([
                'name' => $program->name,
                'description' => $program->description,
                'position' => $program->position,
                'lock_version' => $nextLockVersion,
                'updated_at' => $program->updatedAt,
            ]);

        if ($affected !== 1) {
            throw ProgramConcurrencyException::forProgram($program->id);
        }

        $this->syncSelections($program);
        $program->lockVersion = $nextLockVersion;
    }

    public function reorder(string $planId, array $orderedProgramIds, string $now): void
    {
        $this->connection->transaction(function () use ($planId, $orderedProgramIds, $now): void {
            $records = ProgramRecord::query()
                ->where('plan_id', $planId)
                ->orderBy('position')
                ->lockForUpdate()
                ->get()
                ->keyBy(static fn (ProgramRecord $record): string => (string) $record->id);

            $currentIds = $records->keys()->sort()->values()->all();
            $incoming = array_values($orderedProgramIds);
            $sortedIncoming = $incoming;
            sort($sortedIncoming);

            if ($currentIds !== $sortedIncoming || count($incoming) !== count(array_unique($incoming))) {
                throw ProgramReorderConflictException::forPlan($planId);
            }

            foreach ($incoming as $index => $programId) {
                ProgramRecord::query()
                    ->whereKey($programId)
                    ->where('plan_id', $planId)
                    ->update([
                        'position' => 1_000_000 + $index + 1,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($incoming as $index => $programId) {
                $affected = ProgramRecord::query()
                    ->whereKey($programId)
                    ->where('plan_id', $planId)
                    ->update([
                        'position' => $index + 1,
                        'lock_version' => ((int) $records[$programId]->lock_version) + 1,
                        'updated_at' => $now,
                    ]);

                if ($affected !== 1) {
                    throw ProgramReorderConflictException::forPlan($planId);
                }
            }
        });
    }

    private function syncSelections(Program $program): void
    {
        ProgramModuleSelectionRecord::query()->where('program_id', $program->id)->delete();
        $selections = $this->selectionAttributes($program);
        if ($selections !== []) {
            ProgramModuleSelectionRecord::query()->insert($selections);
        }
    }

    private function hydrate(ProgramRecord $record): Program
    {
        return new Program(
            id: (string) $record->id,
            planId: (string) $record->plan_id,
            code: (string) $record->code,
            name: (string) $record->name,
            description: $record->description === null ? null : (string) $record->description,
            position: (int) $record->position,
            lockVersion: (int) $record->lock_version,
            selections: $record->selections->map(
                static fn (ProgramModuleSelectionRecord $selection): ProgramModuleSelection => new ProgramModuleSelection(
                    id: (string) $selection->id,
                    programId: (string) $selection->program_id,
                    moduleId: (string) $selection->module_id,
                    createdAt: $selection->created_at->utc()->toISOString(),
                )
            )->all(),
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function programAttributes(Program $program): array
    {
        return [
            'id' => $program->id,
            'plan_id' => $program->planId,
            'code' => $program->code,
            'name' => $program->name,
            'description' => $program->description,
            'position' => $program->position,
            'lock_version' => $program->lockVersion,
            'created_at' => $program->createdAt,
            'updated_at' => $program->updatedAt,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function selectionAttributes(Program $program): array
    {
        return array_map(
            static fn (ProgramModuleSelection $selection): array => [
                'id' => $selection->id,
                'program_id' => $program->id,
                'module_id' => $selection->moduleId,
                'created_at' => $selection->createdAt,
            ],
            $program->selections,
        );
    }
}
