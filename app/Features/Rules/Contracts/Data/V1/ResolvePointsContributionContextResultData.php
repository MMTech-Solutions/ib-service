<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolvePointsContributionContextResultData extends Data
{
    public function __construct(
        public readonly ?PointsContributionContextData $context,
    ) {}

    public function found(): bool
    {
        return $this->context !== null;
    }

    public static function foundContext(PointsContributionContextData $context): self
    {
        return new self(context: $context);
    }

    public static function absent(): self
    {
        return new self(context: null);
    }
}
