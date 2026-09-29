<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class BrokerInstrumentCatalogUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('BROKER_INSTRUMENT_CATALOG_UNAVAILABLE', 'Broker instrument catalogue is currently unavailable.', 503);
    }
}
