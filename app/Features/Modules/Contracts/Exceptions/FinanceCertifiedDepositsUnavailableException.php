<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class FinanceCertifiedDepositsUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('FINANCE_CERTIFIED_DEPOSITS_UNAVAILABLE', 'Certified deposits are temporarily unavailable.', 503);
    }
}
