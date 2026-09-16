<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\RejectSubscriptionRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class RejectSubscriptionCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $actorExternalUserId,
        public readonly string $reason,
    ) {}

    public static function fromRequest(RejectSubscriptionRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
            actorExternalUserId: $userContext->id(),
            reason: trim((string) $validated['reason']),
        );
    }
}
