<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\UpdateSubscriptionRewardRatesRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class UpdateSubscriptionRewardRatesCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $personalRate,
        public readonly bool $isMaster,
        public readonly string $masterRate,
        public readonly int $lockVersion,
        public readonly string $actorExternalUserId,
        public readonly ?string $reason,
    ) {}

    public static function fromRequest(UpdateSubscriptionRewardRatesRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
            personalRate: (string) $validated['personal_rate'],
            isMaster: (bool) $validated['is_master'],
            masterRate: (string) $validated['master_rate'],
            lockVersion: (int) $validated['lock_version'],
            actorExternalUserId: $userContext->id(),
            reason: array_key_exists('reason', $validated) && $validated['reason'] !== null
                ? trim((string) $validated['reason'])
                : null,
        );
    }
}
