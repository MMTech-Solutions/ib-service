<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidNegativePnlReferralsException extends ApiException
{
    public static function create(): self
    {
        return new self('INVALID_NEGATIVE_PNL_REFERRALS', 'IAM returned incompatible PnL referral evidence.', 502);
    }
}
