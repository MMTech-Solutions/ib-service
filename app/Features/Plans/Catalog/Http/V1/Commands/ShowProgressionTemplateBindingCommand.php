<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\ShowProgressionTemplateBindingRequest;
use Spatie\LaravelData\Data;

final class ShowProgressionTemplateBindingCommand extends Data
{
    public function __construct(public readonly string $planId, public readonly string $bindingId) {}

    public static function fromRequest(ShowProgressionTemplateBindingRequest $request): self
    {
        return new self((string) $request->validated('plan'), (string) $request->validated('binding'));
    }
}
