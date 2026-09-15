<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class RuleVersionData extends Data
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $id,
        public readonly string $rule_id,
        public readonly int $version_number,
        public readonly string $status,
        public readonly int $schema_version,
        public readonly array $configuration,
        public readonly ?string $published_at,
        public readonly int $lock_version,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
