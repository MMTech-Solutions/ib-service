<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionChangesQueryData;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionChangesRequest;
use Spatie\LaravelData\Data;

final class ListSubscriptionChangesCommand extends Data
{
    public function __construct(public readonly SubscriptionChangesQueryData $query) {}

    public static function fromRequest(ListSubscriptionChangesRequest $request): self
    {
        $validated = $request->validated();

        return new self(new SubscriptionChangesQueryData(
            subscriptionId: $validated['subscription'],
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            action: $validated['action'] ?? null,
            actorKind: $validated['actor_kind'] ?? null,
            occurredAtFrom: $validated['occurred_at_from'] ?? null,
            occurredAtTo: $validated['occurred_at_to'] ?? null,
        ));
    }
}
