<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionPlacementsQueryData;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionPlacementsRequest;
use Spatie\LaravelData\Data;

final class ListSubscriptionPlacementsCommand extends Data
{
    public function __construct(public readonly SubscriptionPlacementsQueryData $query) {}

    public static function fromRequest(ListSubscriptionPlacementsRequest $request): self
    {
        $validated = $request->validated();

        return new self(new SubscriptionPlacementsQueryData(
            subscriptionId: $validated['subscription'],
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            programId: $validated['program_id'] ?? null,
            isFixed: isset($validated['is_fixed']) ? (bool) $validated['is_fixed'] : null,
            overlapFrom: $validated['overlap_from'] ?? null,
            overlapUntil: $validated['overlap_until'] ?? null,
        ));
    }
}
