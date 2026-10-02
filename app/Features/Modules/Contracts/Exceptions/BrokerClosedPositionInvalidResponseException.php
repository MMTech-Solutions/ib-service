<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class BrokerClosedPositionInvalidResponseException extends ApiException
{
    public static function create(): self
    {
        return new self('BROKER_CLOSED_POSITION_INVALID_RESPONSE', 'Broker returned an invalid closed position response.', 502);
    }
}
