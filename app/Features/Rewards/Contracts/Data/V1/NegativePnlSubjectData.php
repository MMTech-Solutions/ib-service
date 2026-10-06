<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlSubjectData extends Data
{
    public function __construct(public readonly string $external_user_id) {}
}
