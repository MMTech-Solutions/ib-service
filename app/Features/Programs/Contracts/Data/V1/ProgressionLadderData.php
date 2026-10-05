<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ProgressionLadderData extends Data
{
    /** @param list<array{program_id: string, position: int, entry_threshold: string}> $programs */
    public function __construct(public readonly array $programs) {}
}
