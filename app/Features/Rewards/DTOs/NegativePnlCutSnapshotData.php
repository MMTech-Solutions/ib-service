<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use Spatie\LaravelData\Data;

final class NegativePnlCutSnapshotData extends Data
{
    public function __construct(
        public readonly string $identity_key,
        public readonly string $module_id,
        public readonly string $subscription_id,
        public readonly string $account_id,
        public readonly string $server_group_id,
        public readonly string $cadence,
        public readonly NegativePnlPeriodData $period,
    ) {}
}
