<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class RuleDetailData extends Data
{
    /**
     * @param  list<RuleVersionData>  $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $plan_id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly string $strategy_type,
        public readonly int $lock_version,
        public readonly array $versions,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
