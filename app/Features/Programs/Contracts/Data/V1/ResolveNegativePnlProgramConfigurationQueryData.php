<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlProgramConfigurationQueryData extends Data
{
    public function __construct(public readonly string $program_id, public readonly ?string $module_id = null, public readonly ?string $server_group_id = null, public readonly ?string $occurred_at = null) {}
}
