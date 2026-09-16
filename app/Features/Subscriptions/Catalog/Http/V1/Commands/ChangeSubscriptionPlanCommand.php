<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ChangeSubscriptionPlanRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ChangeSubscriptionPlanCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $planId,
        public readonly ?string $programId,
        public readonly int $lockVersion,
        public readonly string $actorExternalUserId,
        public readonly ?string $reason,
    ) {}

    public static function fromRequest(ChangeSubscriptionPlanRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
            planId: (string) $validated['plan_id'],
            programId: array_key_exists('program_id', $validated) && $validated['program_id'] !== null
                ? (string) $validated['program_id']
                : null,
            lockVersion: (int) $validated['lock_version'],
            actorExternalUserId: $userContext->id(),
            reason: array_key_exists('reason', $validated) && $validated['reason'] !== null
                ? trim((string) $validated['reason'])
                : null,
        );
    }
}
