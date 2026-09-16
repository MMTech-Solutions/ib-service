<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Ended = 'ended';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this === self::Rejected || $this === self::Ended;
    }
}
