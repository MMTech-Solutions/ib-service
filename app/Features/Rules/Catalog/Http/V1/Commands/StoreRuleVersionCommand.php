<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\StoreRuleVersionRequest;
use Spatie\LaravelData\Data;

final class StoreRuleVersionCommand extends Data
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly int $schemaVersion,
        public readonly array $configuration,
    ) {}

    public static function fromRequest(StoreRuleVersionRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            schemaVersion: (int) $validated['schema_version'],
            configuration: $validated['configuration'],
        );
    }
}
