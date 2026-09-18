<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ActivityEvaluationListQueryData extends Data
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $planId = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?EvaluationStatus $status = null,
        public readonly ?ExclusionReason $exclusionReason = null,
        public readonly ?CarbonImmutable $occurredAtFrom = null,
        public readonly ?CarbonImmutable $occurredAtTo = null,
    ) {}
}
