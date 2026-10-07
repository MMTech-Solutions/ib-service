<?php

declare(strict_types=1);

namespace App\Features\Settings\Contracts\Data\V1;

use OutOfBoundsException;
use Spatie\LaravelData\Data;

final class ResolvedSettingsData extends Data
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values) {}

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            throw new OutOfBoundsException('Setting was not captured in this operation.');
        }

        return $this->values[$key];
    }
}
