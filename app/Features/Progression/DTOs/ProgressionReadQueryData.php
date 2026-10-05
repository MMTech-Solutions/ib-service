<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionReadQueryData extends Data
{
    /** @param array{plan_id?: string, subscription_id?: string, module_id?: string, source_activity_id?: string, status?: string, window_from?: string, window_to?: string, resolved_at_from?: string, resolved_at_to?: string} $filters */
    public function __construct(public readonly string $resource, public readonly ?string $id, public readonly ?string $runId, public readonly array $filters, public readonly int $page = 1, public readonly int $perPage = 100) {}
}
