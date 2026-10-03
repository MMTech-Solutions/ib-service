<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use Spatie\LaravelData\Data;

final class NegativePnlAccountCutData extends Data
{
    public function __construct(public readonly NegativePnlPeriodData $cut, public readonly bool $requires_baseline) {}
}
