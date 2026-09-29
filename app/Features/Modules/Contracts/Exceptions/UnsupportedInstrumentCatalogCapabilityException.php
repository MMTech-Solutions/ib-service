<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class UnsupportedInstrumentCatalogCapabilityException extends ApiException
{
    public static function forModule(string $moduleId): self
    {
        return new self(
            'UNSUPPORTED_INSTRUMENT_CATALOG_CAPABILITY',
            "Module [{$moduleId}] has no instrument catalogue source registered.",
            422,
        );
    }
}
