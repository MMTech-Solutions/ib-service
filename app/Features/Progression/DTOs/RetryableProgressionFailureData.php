<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use Spatie\LaravelData\Data;

final class RetryableProgressionFailureData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $source_activity_id,
        public readonly string $failure_code,
        public readonly ?string $beneficiary_external_user_id = null,
        public readonly ?int $distribution_level = null,
    ) {}

    public static function activity(NormalizedActivityData $activity, string $failureCode): self
    {
        return new self($activity->module_id, $activity->source_activity_id, $failureCode);
    }
}
