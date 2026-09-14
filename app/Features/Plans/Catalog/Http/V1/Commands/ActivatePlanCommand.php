<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\ActivatePlanRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ActivatePlanCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly int $lockVersion,
        public readonly string $actorIamId,
        public readonly string $reason,
    ) {}

    public static function fromRequest(ActivatePlanRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            lockVersion: (int) $validated['lock_version'],
            actorIamId: $userContext->id(),
            reason: trim((string) $validated['reason']),
        );
    }
}
