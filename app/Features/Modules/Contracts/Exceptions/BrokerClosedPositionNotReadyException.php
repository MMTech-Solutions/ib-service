<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class BrokerClosedPositionNotReadyException extends ApiException
{
    public static function create(): self
    {
        return new self('BROKER_CLOSED_POSITION_NOT_READY', 'Broker closed position is not ready yet.', 409);
    }
}
