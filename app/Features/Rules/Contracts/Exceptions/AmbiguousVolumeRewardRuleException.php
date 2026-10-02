<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class AmbiguousVolumeRewardRuleException extends ApiException
{
    public static function forContext(): self
    {
        return new self('AMBIGUOUS_VOLUME_REWARD_RULE', 'Multiple volume reward rules apply to this context.', 422);
    }
}
