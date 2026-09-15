<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Http\V1\Commands;

use App\Features\Rules\Catalog\Http\V1\Requests\UpdateRuleVersionRequest;
use Spatie\LaravelData\Data;

final class UpdateRuleVersionCommand extends Data
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly string $versionId,
        public readonly int $lockVersion,
        public readonly int $schemaVersion,
        public readonly array $configuration,
    ) {}

    public static function fromRequest(UpdateRuleVersionRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            versionId: (string) $validated['version'],
            lockVersion: (int) $validated['lock_version'],
            schemaVersion: (int) $validated['schema_version'],
            configuration: $validated['configuration'],
        );
    }
}
