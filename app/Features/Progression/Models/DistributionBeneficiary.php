<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use InvalidArgumentException;

final class DistributionBeneficiary
{
    public function __construct(public readonly string $beneficiaryExternalUserId, public readonly int $distributionLevel)
    {
        if ($this->distributionLevel < 0) {
            throw new InvalidArgumentException('Distribution level must be non-negative.');
        }
    }

    public function key(): string
    {
        return $this->beneficiaryExternalUserId.'|'.$this->distributionLevel;
    }
}
