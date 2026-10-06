<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class NegativePnlProcessingPeriodData extends Data
{
    /** @param array<string, list<NegativePnlAccountCutData>> $receipts */
    public function __construct(public readonly string $id, public readonly string $occurred_until, public readonly string $status, public readonly NegativePnlFrozenInputsData $inputs, public readonly array $receipts, public readonly string $occurred_from) {}
}
