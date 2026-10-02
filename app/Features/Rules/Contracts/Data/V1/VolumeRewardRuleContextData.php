<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardRuleContextData extends Data
{
    public function __construct(public readonly ?string $rule_assignment_id, public readonly ?string $rule_id, public readonly ?string $rule_version_id) {}

    public function found(): bool
    {
        return $this->rule_assignment_id !== null;
    }

    public static function absent(): self
    {
        return new self(null, null, null);
    }
}
