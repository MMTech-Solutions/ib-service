<?php

declare(strict_types=1);

namespace App\Features\Settings\DTOs;

use Spatie\LaravelData\Data;

final class SettingDefinitionData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $domain,
        public readonly string $section,
        public readonly ?string $provider,
        public readonly int $position,
        public readonly string $name,
        public readonly string $description,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly bool $sensitive = false,
        public readonly int $schema_version = 1,
        public readonly ?int $minimum = null,
        public readonly int $max_length = 2048,
    ) {}
}
