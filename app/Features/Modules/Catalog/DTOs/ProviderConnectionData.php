<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ProviderConnectionData extends Data
{
    public function __construct(public readonly string $provider, public readonly string $expected_service, public readonly string $base_url, public readonly string $internal_prefix, public readonly ?string $internal_token, public readonly string $source_service, public readonly int $timeout_seconds) {}
}
