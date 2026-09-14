<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Contracts\Repositories;

use App\Features\Programs\Catalog\Models\Program;
use Closure;

interface ProgramRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?Program;

    public function findByPlanAndId(string $planId, string $programId): ?Program;

    /** @return list<Program> */
    public function listByPlanId(string $planId): array;

    public function nextPosition(string $planId): int;

    public function create(Program $program): void;

    public function update(Program $program, int $expectedLockVersion): void;

    /**
     * @param  list<string>  $orderedProgramIds
     */
    public function reorder(string $planId, array $orderedProgramIds, string $now): void;
}
