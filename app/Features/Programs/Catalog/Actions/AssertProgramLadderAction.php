<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Actions;

use App\Features\Programs\Catalog\Exceptions\ProgramLadderInvalidException;
use App\Features\Programs\Catalog\Models\Program;

final class AssertProgramLadderAction
{
    /**
     * @param  list<Program>  $orderedPrograms
     */
    public function assert(array $orderedPrograms): void
    {
        $previous = null;
        foreach (array_values($orderedPrograms) as $program) {
            if ($program->entryThreshold < 0) {
                throw ProgramLadderInvalidException::forPlan();
            }

            if ($previous !== null && $program->entryThreshold <= $previous->entryThreshold) {
                throw ProgramLadderInvalidException::forPlan();
            }

            $previous = $program;
        }
    }
}
