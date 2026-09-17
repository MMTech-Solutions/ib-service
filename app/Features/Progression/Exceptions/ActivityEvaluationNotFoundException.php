<?php

declare(strict_types=1);

namespace App\Features\Progression\Exceptions;

use App\Support\Exceptions\ApiException;

final class ActivityEvaluationNotFoundException extends ApiException
{
    public static function forIdempotencyKey(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
    ): self {
        return new self(
            'PROGRESSION_EVALUATION_NOT_FOUND',
            "Activity evaluation for module [{$moduleId}], source activity [{$sourceActivityId}] and beneficiary [{$beneficiaryExternalUserId}] was not found.",
            404,
        );
    }
}
