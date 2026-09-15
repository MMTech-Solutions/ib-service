<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\ListRuleVersionsRequest;
use Spatie\LaravelData\Data;

final class ListRuleVersionsCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
    ) {}

    public static function fromRequest(ListRuleVersionsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
        );
    }
}
