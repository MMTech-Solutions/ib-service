<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class RewardFinancialOperationNotAllowedException extends ApiException
{
    public static function create(): self
    {
        return new self('REWARD_FINANCIAL_OPERATION_NOT_ALLOWED', 'The reward cannot perform this financial operation in its current state.', 422);
    }
}
