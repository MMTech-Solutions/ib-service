<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\ShowPaymentTemplateBindingRequest;
use Spatie\LaravelData\Data;

final class ShowPaymentTemplateBindingCommand extends Data
{
    public function __construct(public readonly string $planId, public readonly string $bindingId) {}

    public static function fromRequest(ShowPaymentTemplateBindingRequest $request): self
    {
        return new self((string) $request->validated('plan'), (string) $request->validated('binding'));
    }
}
