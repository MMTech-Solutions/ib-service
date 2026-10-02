<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class RewardNotFoundException extends ApiException
{
    public static function forId(string $rewardId): self
    {
        return new self('REWARD_NOT_FOUND', 'The requested reward was not found.', 404);
    }
}
