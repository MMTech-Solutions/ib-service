<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ProgressionResultReferenceData extends Data
{
    public function __construct(public readonly string $run_result_id, public readonly string $run_id) {}
}
