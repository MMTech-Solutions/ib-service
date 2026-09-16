<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum PlacementCondition: string
{
    case Fixed = 'fixed';
    case Unfixed = 'unfixed';

    public function isFixed(): bool
    {
        return $this === self::Fixed;
    }

    public static function fromBoolean(bool $isFixed): self
    {
        return $isFixed ? self::Fixed : self::Unfixed;
    }
}
