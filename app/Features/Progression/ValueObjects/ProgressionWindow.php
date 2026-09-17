<?php

declare(strict_types=1);

namespace App\Features\Progression\ValueObjects;

use App\Features\Progression\Exceptions\InvalidProgressionWindowException;
use Carbon\CarbonImmutable;

/**
 * Ventana UTC semiabierta en el sentido de dominio de extremos explícitos:
 * endsAt debe ser estrictamente posterior a startsAt.
 */
final class ProgressionWindow
{
    private function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
    ) {}

    public static function of(CarbonImmutable $startsAt, CarbonImmutable $endsAt): self
    {
        $start = $startsAt->utc();
        $end = $endsAt->utc();

        if ($end->lessThanOrEqualTo($start)) {
            throw InvalidProgressionWindowException::forOrder($start->toISOString(), $end->toISOString());
        }

        return new self($start, $end);
    }

    public function startsAtIso(): string
    {
        return $this->startsAt->toISOString();
    }

    public function endsAtIso(): string
    {
        return $this->endsAt->toISOString();
    }
}
