<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionExecutionReportData extends Data
{
    /**
     * @param  array<string, int>  $counts
     * @param  array<string, list<string>>  $ids
     */
    public function __construct(
        public readonly int $version,
        public readonly string $operation,
        public readonly string $execution_id,
        public readonly string $status,
        public readonly ?string $observed_domain_time_utc,
        public readonly ?string $context_id,
        public readonly ?int $sequence,
        public readonly ?string $outcome,
        public readonly array $counts,
        public readonly array $ids,
    ) {}
}
