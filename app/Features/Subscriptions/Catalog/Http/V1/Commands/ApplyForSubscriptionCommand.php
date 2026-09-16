<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ApplyForSubscriptionRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ApplyForSubscriptionCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $externalUserId,
        public readonly string $actorExternalUserId,
        public readonly ?string $reason,
    ) {}

    public static function fromRequest(ApplyForSubscriptionRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();
        $actorId = $userContext->id();

        return new self(
            planId: (string) $validated['plan_id'],
            externalUserId: $actorId,
            actorExternalUserId: $actorId,
            reason: array_key_exists('reason', $validated) && $validated['reason'] !== null
                ? trim((string) $validated['reason'])
                : null,
        );
    }
}
