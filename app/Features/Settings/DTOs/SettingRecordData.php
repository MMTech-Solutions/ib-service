<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SettingRecordData extends Data
{
    public function __construct(
        public readonly SettingDefinitionData $definition,
        public readonly mixed $value,
        public readonly string $mode,
        public readonly int $lock_version,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
