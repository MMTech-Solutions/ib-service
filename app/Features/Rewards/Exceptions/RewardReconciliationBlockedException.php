<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class RewardReconciliationBlockedException extends ApiException
{
    public static function create(): self
    {
        return new self('REWARD_RECONCILIATION_BLOCKED', 'The reward is blocked pending financial reconciliation.', 409);
    }
}
