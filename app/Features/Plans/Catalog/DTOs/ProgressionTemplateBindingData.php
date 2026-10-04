<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionTemplateBindingData extends Data
{
    public function __construct(public readonly string $id, public readonly string $plan_id, public readonly string $template_version_id, public readonly string $created_at) {}
}
