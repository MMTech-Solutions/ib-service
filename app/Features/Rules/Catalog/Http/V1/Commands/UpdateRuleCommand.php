<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\UpdateRuleRequest;
use Spatie\LaravelData\Data;

final class UpdateRuleCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly int $lockVersion,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly bool $hasName,
        public readonly bool $hasDescription,
    ) {}

    public static function fromRequest(UpdateRuleRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            lockVersion: (int) $validated['lock_version'],
            name: array_key_exists('name', $validated) ? trim((string) $validated['name']) : null,
            description: array_key_exists('description', $validated) && $validated['description'] !== null
                ? (string) $validated['description']
                : null,
            hasName: array_key_exists('name', $validated),
            hasDescription: array_key_exists('description', $validated),
        );
    }
}
