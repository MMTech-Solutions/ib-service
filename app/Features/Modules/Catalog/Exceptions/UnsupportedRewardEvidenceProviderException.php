<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Exceptions;

use RuntimeException;

final class UnsupportedRewardEvidenceProviderException extends RuntimeException
{
    public function __construct(public readonly string $provider_code)
    {
        parent::__construct("Unsupported reward evidence provider [{$provider_code}].");
    }
}
