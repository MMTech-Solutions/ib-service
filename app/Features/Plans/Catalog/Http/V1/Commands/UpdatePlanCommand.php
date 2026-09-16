<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\UpdatePlanRequest;
use Spatie\LaravelData\Data;

final class UpdatePlanCommand extends Data
{
    /**
     * @param  list<string>|null  $moduleIds
     */
    public function __construct(
        public readonly string $planId,
        public readonly int $lockVersion,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly bool $hasName,
        public readonly bool $hasDescription,
        public readonly ?array $moduleIds,
        public readonly ?bool $requiresApproval,
        public readonly bool $hasRequiresApproval,
    ) {}

    public static function fromRequest(UpdatePlanRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            lockVersion: (int) $validated['lock_version'],
            name: array_key_exists('name', $validated) ? trim((string) $validated['name']) : null,
            description: array_key_exists('description', $validated) && $validated['description'] !== null
                ? (string) $validated['description']
                : null,
            hasName: array_key_exists('name', $validated),
            hasDescription: array_key_exists('description', $validated),
            moduleIds: array_key_exists('module_ids', $validated)
                ? array_values(array_unique($validated['module_ids'] ?? []))
                : null,
            requiresApproval: array_key_exists('requires_approval', $validated)
                ? (bool) $validated['requires_approval']
                : null,
            hasRequiresApproval: array_key_exists('requires_approval', $validated),
        );
    }
}
