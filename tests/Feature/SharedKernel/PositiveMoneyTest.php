<?php

declare(strict_types=1);

namespace Tests\Feature\SharedKernel;

use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class PositiveMoneyTest extends TestCase
{
    public function test_it_converts_major_units_using_the_configured_currency_precision(): void
    {
        self::assertSame(1050, PositiveMoney::fromDecimalMajor('10.50', Currency::from('USD', 2))->minorUnits);
        self::assertSame(10, PositiveMoney::fromDecimalMajor('10', Currency::from('JPY', 0))->minorUnits);
    }

    public function test_it_rejects_fractional_amounts_below_currency_precision(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PositiveMoney::fromDecimalMajor('10.50', Currency::from('JPY', 0));
    }

    public function test_it_rounds_major_units_half_up_to_the_currency_precision(): void
    {
        self::assertSame(11, PositiveMoney::fromDecimalMajorRounded('0.105', Currency::from('USD', 2))->minorUnits);
        self::assertSame(1, PositiveMoney::fromDecimalMajorRounded('0.5', Currency::from('JPY', 0))->minorUnits);
    }

    public function test_it_uses_laravel_validation_for_currency(): void
    {
        $this->expectException(ValidationException::class);
        Currency::from('US', 2);
    }

    public function test_it_uses_laravel_validation_for_the_decimal_shape(): void
    {
        $this->expectException(ValidationException::class);
        PositiveMoney::fromDecimalMajor('invalid', Currency::from('USD', 2));
    }
}
