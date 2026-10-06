<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\DTOs;

use Spatie\LaravelData\Data;

final class CpaConfigurationData extends Data
{
    public function __construct(public readonly string $id, public readonly string $program_id, public readonly string $rule_id, public readonly string $rule_version_id, public readonly string $starts_at, public readonly ?string $ends_at) {}
}
