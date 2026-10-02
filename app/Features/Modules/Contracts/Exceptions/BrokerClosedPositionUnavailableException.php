<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class BrokerClosedPositionUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('BROKER_CLOSED_POSITION_UNAVAILABLE', 'Broker closed position is currently unavailable.', 503);
    }
}
