<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use RuntimeException;

final class UnsupportedNegativePnlPeriodsProviderException extends RuntimeException
{
    public function __construct(public readonly string $provider_code)
    {
        parent::__construct("Unsupported negative PnL periods provider [{$provider_code}].");
    }
}
