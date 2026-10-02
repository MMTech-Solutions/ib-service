<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class RecordVolumeRewardEventData extends Data
{
    public function __construct(public readonly string $module_id, public readonly string $order_id, public readonly string $external_trader_id, public readonly array $transport_snapshot) {}
}
