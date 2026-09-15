<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\StoreRuleRequest;
use Spatie\LaravelData\Data;

final class StoreRuleCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $strategyType,
    ) {}

    public static function fromRequest(StoreRuleRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            name: trim((string) $validated['name']),
            description: array_key_exists('description', $validated) && $validated['description'] !== null
                ? (string) $validated['description']
                : null,
            strategyType: (string) $validated['strategy_type'],
        );
    }
}
