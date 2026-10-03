<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class NegativePnlReferralsUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('NEGATIVE_PNL_REFERRALS_UNAVAILABLE', 'PnL referral evidence is temporarily unavailable.', 503);
    }
}
