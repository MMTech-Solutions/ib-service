<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class NegativePnlPeriodsUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self(
            'NEGATIVE_PNL_PERIODS_UNAVAILABLE',
            'Broker negative PnL periods are currently unavailable.',
            503,
        );
    }
}
