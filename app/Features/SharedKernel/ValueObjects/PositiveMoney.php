<?php

declare(strict_types=1);

namespace App\Features\SharedKernel\ValueObjects;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class PositiveMoney
{
    private function __construct(
        public readonly int $minorUnits,
        public readonly Currency $currency,
    ) {}

    public static function fromDecimalMajor(string $amount, Currency $currency): self
    {
        Validator::validate(['amount' => $amount], [
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
        ]);

        if (bccomp($amount, '0', 8) !== 1) {
            throw new InvalidArgumentException('Money amount must be a positive decimal string.');
        }

        $fraction = explode('.', $amount, 2)[1] ?? '';
        if (strlen(rtrim($fraction, '0')) > $currency->precision()) {
            throw new InvalidArgumentException('Money amount exceeds currency precision.');
        }

        $factor = bcpow('10', (string) $currency->precision(), 0);
        $minorUnits = bcmul($amount, $factor, 0);
        if (bccomp($minorUnits, (string) PHP_INT_MAX, 0) === 1) {
            throw new InvalidArgumentException('Money amount exceeds supported minor units.');
        }

        return new self((int) $minorUnits, $currency);
    }

    public static function fromDecimalMajorRounded(string $amount, Currency $currency): self
    {
        Validator::validate(['amount' => $amount], [
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
        ]);

        if (bccomp($amount, '0', max(8, strlen(explode('.', $amount, 2)[1] ?? ''))) !== 1) {
            throw new InvalidArgumentException('Money amount must be a positive decimal string.');
        }

        $factor = bcpow('10', (string) $currency->precision(), 0);
        $scaled = bcmul($amount, $factor, 12);
        $minorUnits = bcadd($scaled, '0.5', 0);
        if (bccomp($minorUnits, (string) PHP_INT_MAX, 0) === 1) {
            throw new InvalidArgumentException('Money amount exceeds supported minor units.');
        }

        return new self((int) $minorUnits, $currency);
    }
}
