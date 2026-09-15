<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\ShowRuleVersionRequest;
use Spatie\LaravelData\Data;

final class ShowRuleVersionCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly string $versionId,
    ) {}

    public static function fromRequest(ShowRuleVersionRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            versionId: (string) $validated['version'],
        );
    }
}
