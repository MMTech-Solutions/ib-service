<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\ListPaymentTemplateBindingsRequest;
use Spatie\LaravelData\Data;

final class ListPaymentTemplateBindingsCommand extends Data
{
    public function __construct(public readonly string $planId, public readonly int $page, public readonly int $perPage) {}

    public static function fromRequest(ListPaymentTemplateBindingsRequest $request): self
    {
        return new self((string) $request->validated('plan'), (int) $request->validated('page', 1), (int) $request->validated('per_page', 100));
    }
}
