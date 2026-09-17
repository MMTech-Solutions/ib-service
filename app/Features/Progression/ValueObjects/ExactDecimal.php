<?php

declare(strict_types=1);

namespace App\Features\Progression\ValueObjects;

use App\Features\Progression\Exceptions\InvalidExactDecimalException;

/**
 * Decimal exacto con escala máxima de ocho dígitos fraccionarios (BR-POINTS-007).
 */
final class ExactDecimal
{
    public const MAX_SCALE = 8;

    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value) !== 1) {
            throw InvalidExactDecimalException::forValue($value, 'Value must be a canonical decimal string.');
        }

        if (str_contains($value, '.')) {
            $fraction = substr($value, (int) strpos($value, '.') + 1);
            if (strlen($fraction) > self::MAX_SCALE) {
                throw InvalidExactDecimalException::forValue(
                    $value,
                    'Value exceeds the maximum scale of eight decimal places.',
                );
            }
        }

        return new self(self::canonicalize($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function multiply(self $other): self
    {
        $product = bcmul($this->value, $other->value, self::MAX_SCALE * 2);
        $canonical = self::canonicalize($product);

        if (str_contains($canonical, '.')) {
            $fraction = substr($canonical, (int) strpos($canonical, '.') + 1);
            if (strlen($fraction) > self::MAX_SCALE) {
                throw InvalidExactDecimalException::forValue(
                    $canonical,
                    'Product exceeds the maximum scale of eight decimal places.',
                );
            }
        }

        return new self($canonical);
    }

    public function equals(self $other): bool
    {
        return bccomp($this->value, $other->value, self::MAX_SCALE) === 0;
    }

    private static function canonicalize(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value === '-0' ? '0' : $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }
}
