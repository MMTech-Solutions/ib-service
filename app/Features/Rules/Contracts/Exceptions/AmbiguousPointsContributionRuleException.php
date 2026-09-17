<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class AmbiguousPointsContributionRuleException extends ApiException
{
    public static function forContext(string $programId, string $moduleId, string $unitCode, string $occurredAt): self
    {
        return new self(
            'AMBIGUOUS_POINTS_CONTRIBUTION_RULE',
            "More than one active points_per_quantity_unit assignment matches program [{$programId}], module [{$moduleId}] and unit [{$unitCode}] at [{$occurredAt}].",
            409,
        );
    }
}
