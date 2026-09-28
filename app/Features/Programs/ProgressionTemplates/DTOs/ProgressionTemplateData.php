<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionTemplateData extends Data
{
    /**
     * @param  list<ProgressionTemplateVersionData>  $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $lock_version,
        public readonly array $versions,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
