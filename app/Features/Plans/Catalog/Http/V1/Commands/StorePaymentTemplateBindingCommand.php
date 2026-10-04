<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\StorePaymentTemplateBindingRequest;
use Spatie\LaravelData\Data;

final class StorePaymentTemplateBindingCommand extends Data
{
    public function __construct(public readonly string $planId, public readonly string $templateVersionId) {}

    public static function fromRequest(StorePaymentTemplateBindingRequest $request): self
    {
        return new self((string) $request->validated('plan'), (string) $request->validated('template_version_id'));
    }
}
