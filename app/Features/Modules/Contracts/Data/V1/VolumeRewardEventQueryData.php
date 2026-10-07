<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardEventQueryData extends Data
{
    /** @param array<string, mixed> $body */
    public function __construct(public readonly string $topic, public readonly string $event_name, public readonly array $body) {}
}
