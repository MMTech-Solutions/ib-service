<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveReferralUplineQueryData extends Data
{
    public function __construct(public readonly string $source_external_user_id) {}
}
