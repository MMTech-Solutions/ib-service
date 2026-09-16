<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ShowCurrentSubscriptionRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ShowCurrentSubscriptionCommand extends Data
{
    public function __construct(
        public readonly string $externalUserId,
    ) {}

    public static function fromRequest(ShowCurrentSubscriptionRequest $request, UserContext $userContext): self
    {
        return new self(
            externalUserId: $userContext->id(),
        );
    }
}
