<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\StorePlanRequest;
use Spatie\LaravelData\Data;

final class StorePlanCommand extends Data
{
    /**
     * @param  list<string>  $moduleIds
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $moduleIds,
        public readonly bool $requiresApproval,
    ) {}

    public static function fromRequest(StorePlanRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            code: (string) $validated['code'],
            name: trim((string) $validated['name']),
            description: array_key_exists('description', $validated) && $validated['description'] !== null
                ? (string) $validated['description']
                : null,
            moduleIds: array_values(array_unique($validated['module_ids'] ?? [])),
            requiresApproval: array_key_exists('requires_approval', $validated)
                ? (bool) $validated['requires_approval']
                : true,
        );
    }
}
