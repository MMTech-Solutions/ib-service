<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ActivityProviderUnavailableException extends ApiException
{
    public static function create(): self
    {
        return new self('ACTIVITY_PROVIDER_UNAVAILABLE', 'Activity provider unavailable.', 503);
    }
}
