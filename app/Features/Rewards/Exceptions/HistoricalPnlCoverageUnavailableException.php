<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use App\Support\Exceptions\ApiException;

final class HistoricalPnlCoverageUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('HISTORICAL_PNL_COVERAGE_UNAVAILABLE', 'Broker has no historical balance reading for the requested cut.', 422);
    }
}
