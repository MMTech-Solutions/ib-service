<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveRewardBackfillStartData extends Data
{
    public function __construct(public readonly ?string $activated_at) {}
}
