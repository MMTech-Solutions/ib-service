<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicatePointsPerQuantityUnitAssignmentException extends ApiException
{
    public static function forContext(string $programId, string $moduleId, string $unit): self
    {
        return new self(
            'POINTS_PER_QUANTITY_UNIT_ASSIGNMENT_CONFLICT',
            "An active points_per_quantity_unit assignment already exists for program [{$programId}], module [{$moduleId}] and unit [{$unit}].",
            409,
        );
    }
}
