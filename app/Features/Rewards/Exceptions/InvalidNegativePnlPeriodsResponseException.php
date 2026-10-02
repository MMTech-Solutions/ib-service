<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidNegativePnlPeriodsResponseException extends ApiException
{
    public static function create(): self
    {
        return new self(
            'INVALID_NEGATIVE_PNL_PERIODS_RESPONSE',
            'Broker returned an invalid negative PnL periods response.',
            502,
        );
    }
}
