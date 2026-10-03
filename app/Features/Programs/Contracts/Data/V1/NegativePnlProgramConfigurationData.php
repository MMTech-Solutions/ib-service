<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlProgramConfigurationData extends Data
{
    /** @param list<NegativePnlGroupConfigurationData> $groups */
    public function __construct(public readonly string $id, public readonly string $program_id, public readonly string $cadence, public readonly string $actor_id, public readonly string $starts_at, public readonly ?string $ends_at, public readonly ?string $closed_by_actor_id, public readonly array $groups) {}
}
