<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class StoreProgressionTemplateBindingResultData extends Data
{
    public function __construct(public readonly ProgressionTemplateBindingData $binding, public readonly bool $created) {}
}
