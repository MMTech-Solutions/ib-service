<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\Http\V1\Requests\ShowSubscriptionRequest;
use Spatie\LaravelData\Data;

final class ShowSubscriptionCommand extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
    ) {}

    public static function fromRequest(ShowSubscriptionRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            subscriptionId: (string) $validated['subscription'],
        );
    }
}
