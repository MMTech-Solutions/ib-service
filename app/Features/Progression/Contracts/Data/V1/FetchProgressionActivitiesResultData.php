<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class FetchProgressionActivitiesResultData extends Data
{
    /**
     * @param  list<NormalizedActivityData>  $activities
     */
    public function __construct(
        public readonly string $module_id,
        public readonly string $module_condition,
        public readonly bool $provider_invoked,
        public readonly array $activities,
        public readonly ?string $next_cursor = null,
        public readonly ?string $rejection_code = null,
    ) {}

    public function isRejected(): bool
    {
        return $this->rejection_code !== null;
    }
}
