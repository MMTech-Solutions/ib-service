<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveProgressionResultReferencesQueryData extends Data
{
    /** @param list<string> $operationIds */
    public function __construct(public readonly string $subscriptionId, public readonly array $operationIds) {}
}
