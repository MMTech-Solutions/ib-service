<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Http\V1\Commands;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionListQueryData;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Http\V1\Requests\ListSubscriptionsRequest;
use Spatie\LaravelData\Data;

final class ListSubscriptionsCommand extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $planId,
        public readonly ?SubscriptionStatus $status,
        public readonly ?string $externalUserId,
    ) {}

    public static function fromRequest(ListSubscriptionsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            planId: isset($validated['plan_id']) ? (string) $validated['plan_id'] : null,
            status: isset($validated['status'])
                ? SubscriptionStatus::from((string) $validated['status'])
                : null,
            externalUserId: isset($validated['external_user_id'])
                ? (string) $validated['external_user_id']
                : null,
        );
    }

    public function toQueryData(): SubscriptionListQueryData
    {
        return new SubscriptionListQueryData(
            page: $this->page,
            perPage: $this->perPage,
            planId: $this->planId,
            status: $this->status,
            externalUserId: $this->externalUserId,
        );
    }
}
