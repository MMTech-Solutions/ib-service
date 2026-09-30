<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class BrokerProgressionActivityUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('BROKER_PROGRESSION_ACTIVITY_UNAVAILABLE', 'Broker progression activity is currently unavailable.', 503);
    }
}
