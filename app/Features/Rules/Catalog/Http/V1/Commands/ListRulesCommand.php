<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\ListRulesRequest;
use Spatie\LaravelData\Data;

final class ListRulesCommand extends Data
{
    public function __construct(
        public readonly string $planId,
    ) {}

    public static function fromRequest(ListRulesRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
        );
    }
}
