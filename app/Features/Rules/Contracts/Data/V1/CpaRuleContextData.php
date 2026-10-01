<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class CpaRuleContextData extends Data
{
    /** @param array<string, mixed>|null $configuration */
    public function __construct(
        public readonly ?string $rule_assignment_id,
        public readonly ?string $rule_id,
        public readonly ?string $rule_version_id,
        public readonly ?string $module_id,
        public readonly ?array $configuration,
    ) {}

    public function found(): bool
    {
        return $this->rule_assignment_id !== null;
    }

    public static function absent(): self
    {
        return new self(null, null, null, null, null);
    }
}
