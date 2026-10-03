<?php

declare(strict_types=1);

namespace App\Features\Rewards\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class NegativePnlCadence
{
    public static function next(string $cadence, string $instant): string
    {
        $at = CarbonImmutable::parse($instant)->utc();

        return (match ($cadence) {
            'daily' => $at->startOfDay()->addDay(),
            'weekly' => $at->startOfWeek(CarbonImmutable::MONDAY)->addWeek(),
            'monthly' => $at->startOfMonth()->addMonth(),
            'yearly' => $at->startOfYear()->addYear(),
            default => throw new InvalidArgumentException('Unsupported PnL cadence'),
        })->toISOString();
    }
}
