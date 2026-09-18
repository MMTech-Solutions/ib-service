<?php

declare(strict_types=1);

namespace App\Features\Progression\Support;

use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Deriva la ventana UTC fija del plan a partir del período obligatorio y occurred_at.
 */
final class DeriveProgressionWindowFromPeriod
{
    public function derive(string $progressionPeriod, CarbonImmutable $occurredAt): ProgressionWindow
    {
        $instant = $occurredAt->utc();

        return match ($progressionPeriod) {
            'daily' => $this->dailyWindow($instant),
            'weekly' => $this->weeklyWindow($instant),
            'monthly' => $this->monthlyWindow($instant),
            default => throw new InvalidArgumentException(
                "Unsupported progression_period [{$progressionPeriod}].",
            ),
        };
    }

    private function dailyWindow(CarbonImmutable $occurredAt): ProgressionWindow
    {
        $startsAt = $occurredAt->startOfDay();

        return ProgressionWindow::of($startsAt, $startsAt->addDay());
    }

    private function weeklyWindow(CarbonImmutable $occurredAt): ProgressionWindow
    {
        $startsAt = $occurredAt->startOfWeek(CarbonImmutable::MONDAY);

        return ProgressionWindow::of($startsAt, $startsAt->addWeek());
    }

    private function monthlyWindow(CarbonImmutable $occurredAt): ProgressionWindow
    {
        $startsAt = $occurredAt->startOfMonth();

        return ProgressionWindow::of($startsAt, $startsAt->addMonthNoOverflow());
    }
}
