<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramNotOnSubscriptionPlanException extends ApiException
{
    public static function forProgram(string $programId, string $planId): self
    {
        return new self(
            'SUBSCRIPTION_PROGRAM_PLAN_MISMATCH',
            "Program [{$programId}] does not belong to plan [{$planId}].",
            422,
        );
    }
}
