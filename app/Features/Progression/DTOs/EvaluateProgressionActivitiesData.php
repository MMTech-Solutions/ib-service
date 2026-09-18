<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

/**
 * Entrada interna (CLI/job) para evaluar páginas de actividad M3 de un plan y módulo.
 */
final class EvaluateProgressionActivitiesData extends Data
{
    public function __construct(
        public readonly string $plan_id,
        public readonly string $module_id,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly bool $resuming_after_pause = false,
        public readonly ?int $limit = null,
    ) {}
}
