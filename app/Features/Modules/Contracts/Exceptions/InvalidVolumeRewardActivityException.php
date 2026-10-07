<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidVolumeRewardActivityException extends ApiException
{
    public static function create(): self
    {
        return new self('INVALID_VOLUME_REWARD_ACTIVITY', 'Invalid volume reward activity contract.', 422);
    }
}
