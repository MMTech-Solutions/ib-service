<?php

declare(strict_types=1);

namespace Tests\Unit\Progression;

use App\Features\Progression\Exceptions\InvalidExactDecimalException;
use App\Features\Progression\Exceptions\InvalidProgressionWindowException;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class ExactDecimalAndWindowTest extends TestCase
{
    public function test_it_accepts_up_to_eight_fractional_digits(): void
    {
        $decimal = ExactDecimal::fromString('1.12345678');

        self::assertSame('1.12345678', $decimal->value());
    }

    public function test_it_rejects_more_than_eight_fractional_digits(): void
    {
        $this->expectException(InvalidExactDecimalException::class);

        ExactDecimal::fromString('1.123456789');
    }

    public function test_it_multiplies_with_exact_decimal_arithmetic(): void
    {
        $points = ExactDecimal::fromString('100')->multiply(ExactDecimal::fromString('0.1'));

        self::assertSame('10', $points->value());
    }

    public function test_window_requires_strictly_later_end(): void
    {
        $start = CarbonImmutable::parse('2026-09-17T00:00:00Z');

        $this->expectException(InvalidProgressionWindowException::class);

        ProgressionWindow::of($start, $start);
    }
}
