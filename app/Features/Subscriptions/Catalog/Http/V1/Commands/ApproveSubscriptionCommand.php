<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ApproveSubscriptionRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ApproveSubscriptionCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?string $programId,
        public readonly string $actorExternalUserId,
        public readonly ?string $reason,
    ) {}

    public static function fromRequest(ApproveSubscriptionRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
            programId: array_key_exists('program_id', $validated) && $validated['program_id'] !== null
                ? (string) $validated['program_id']
                : null,
            actorExternalUserId: $userContext->id(),
            reason: array_key_exists('reason', $validated) && $validated['reason'] !== null
                ? trim((string) $validated['reason'])
                : null,
        );
    }
}
