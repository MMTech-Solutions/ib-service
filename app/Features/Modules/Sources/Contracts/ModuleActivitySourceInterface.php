<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Contracts;

use App\Features\Modules\Contracts\Data\V1\ProgressionActivityData;
use Carbon\CarbonImmutable;

interface ModuleActivitySourceInterface
{
    public function capabilityCode(): string;

    /**
     * @return list<ProgressionActivityData>
     */
    public function fetchPage(
        string $moduleId,
        CarbonImmutable $occurredFrom,
        CarbonImmutable $occurredUntil,
        ?string $afterOccurredAt,
        ?string $afterSourceActivityId,
        int $limit,
    ): array;
}
