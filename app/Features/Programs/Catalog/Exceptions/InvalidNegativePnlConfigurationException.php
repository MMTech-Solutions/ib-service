<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidNegativePnlConfigurationException extends ApiException
{
    public static function forReason(string $reason): self
    {
        return new self('INVALID_NEGATIVE_PNL_CONFIGURATION', $reason, 422);
    }
}
