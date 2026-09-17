<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveSubscriptionContextResultData extends Data
{
    public function __construct(
        public readonly ?SubscriptionContextData $context,
    ) {}

    public function found(): bool
    {
        return $this->context !== null;
    }

    public static function foundContext(SubscriptionContextData $context): self
    {
        return new self(context: $context);
    }

    public static function absent(): self
    {
        return new self(context: null);
    }
}
