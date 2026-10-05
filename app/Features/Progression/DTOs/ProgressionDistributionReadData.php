<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionDistributionReadData extends Data
{
    /** @param list<array{beneficiary_external_user_id: string, distribution_level: int}> $beneficiaries */
    public function __construct(public readonly string $id, public readonly string $module_id, public readonly string $source_activity_id, public readonly string $source_external_user_id, public readonly string $resolved_at, public readonly array $beneficiaries) {}
}
