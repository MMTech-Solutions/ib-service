<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Models;

use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;
use Carbon\CarbonImmutable;

final class SubscriptionPlacement
{
    public function __construct(
        public readonly string $id,
        public readonly string $subscriptionId,
        public readonly string $programId,
        public readonly PlacementCondition $condition,
        public readonly string $effectiveFrom,
        public ?string $effectiveUntil,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {
        $this->assertInterval();
    }

    public static function open(
        string $id,
        string $subscriptionId,
        string $programId,
        PlacementCondition $condition,
        string $effectiveFrom,
    ): self {
        return new self(
            id: $id,
            subscriptionId: $subscriptionId,
            programId: $programId,
            condition: $condition,
            effectiveFrom: $effectiveFrom,
            effectiveUntil: null,
            createdAt: $effectiveFrom,
            updatedAt: $effectiveFrom,
        );
    }

    public function isOpen(): bool
    {
        return $this->effectiveUntil === null;
    }

    public function isFixed(): bool
    {
        return $this->condition->isFixed();
    }

    public function covers(string $occurredAt): bool
    {
        $instant = CarbonImmutable::parse($occurredAt)->utc();
        $from = CarbonImmutable::parse($this->effectiveFrom)->utc();

        if ($instant->lt($from)) {
            return false;
        }

        if ($this->effectiveUntil === null) {
            return true;
        }

        return $instant->lt(CarbonImmutable::parse($this->effectiveUntil)->utc());
    }

    public function close(string $effectiveUntil): void
    {
        if (! $this->isOpen()) {
            throw SubscriptionInvariantException::withMessage(
                "Placement [{$this->id}] is already closed.",
            );
        }

        $this->effectiveUntil = $effectiveUntil;
        $this->updatedAt = $effectiveUntil;
        $this->assertInterval();
    }

    private function assertInterval(): void
    {
        if ($this->effectiveUntil === null) {
            return;
        }

        $from = CarbonImmutable::parse($this->effectiveFrom)->utc();
        $until = CarbonImmutable::parse($this->effectiveUntil)->utc();

        if ($until->lt($from)) {
            throw SubscriptionInvariantException::withMessage(
                "Placement [{$this->id}] effective_until precedes effective_from.",
            );
        }
    }
}
