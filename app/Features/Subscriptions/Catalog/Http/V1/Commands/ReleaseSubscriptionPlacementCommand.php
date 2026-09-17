<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ReleaseSubscriptionPlacementRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ReleaseSubscriptionPlacementCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly int $lockVersion,
        public readonly string $actorExternalUserId,
        public readonly ?string $reason,
    ) {}

    public static function fromRequest(ReleaseSubscriptionPlacementRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
            lockVersion: (int) $validated['lock_version'],
            actorExternalUserId: $userContext->id(),
            reason: array_key_exists('reason', $validated) && $validated['reason'] !== null
                ? trim((string) $validated['reason'])
                : null,
        );
    }
}
