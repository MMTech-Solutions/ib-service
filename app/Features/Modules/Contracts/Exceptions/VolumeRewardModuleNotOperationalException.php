<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class VolumeRewardModuleNotOperationalException extends ApiException
{
    public static function forCondition(string $condition): self
    {
        return new self('VOLUME_REWARD_MODULE_NOT_OPERATIONAL', "Volume reward module is {$condition}.", 409);
    }
}
