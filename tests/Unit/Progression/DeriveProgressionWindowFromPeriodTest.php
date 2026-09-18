<?php

declare(strict_types=1);

namespace Tests\Unit\Progression;

use App\Features\Progression\Support\DeriveProgressionWindowFromPeriod;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeriveProgressionWindowFromPeriodTest extends TestCase
{
    public function test_it_derives_daily_weekly_and_monthly_utc_windows(): void
    {
        $derive = new DeriveProgressionWindowFromPeriod;
        $instant = CarbonImmutable::parse('2026-09-16T15:30:00Z');

        $daily = $derive->derive('daily', $instant);
        self::assertSame('2026-09-16T00:00:00.000000Z', $daily->startsAtIso());
        self::assertSame('2026-09-17T00:00:00.000000Z', $daily->endsAtIso());

        $weekly = $derive->derive('weekly', $instant);
        self::assertSame('2026-09-14T00:00:00.000000Z', $weekly->startsAtIso());
        self::assertSame('2026-09-21T00:00:00.000000Z', $weekly->endsAtIso());

        $monthly = $derive->derive('monthly', $instant);
        self::assertSame('2026-09-01T00:00:00.000000Z', $monthly->startsAtIso());
        self::assertSame('2026-10-01T00:00:00.000000Z', $monthly->endsAtIso());
    }

    public function test_it_rejects_unknown_periods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DeriveProgressionWindowFromPeriod)->derive('rolling', CarbonImmutable::parse('2026-09-16T15:30:00Z'));
    }
}
