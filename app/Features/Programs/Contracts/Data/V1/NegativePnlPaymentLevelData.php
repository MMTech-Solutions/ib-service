<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlPaymentLevelData extends Data
{
    public function __construct(public readonly int $distribution_level, public readonly string $rate) {}
}
