<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\Http\V1\Requests\StoreProgramRequest;
use Spatie\LaravelData\Data;

final class StoreProgramCommand extends Data
{
    /**
     * @param  list<string>  $moduleIds
     */
    public function __construct(
        public readonly string $planId,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $moduleIds,
    ) {}

    public static function fromRequest(StoreProgramRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            code: (string) $validated['code'],
            name: trim((string) $validated['name']),
            description: array_key_exists('description', $validated) && $validated['description'] !== null
                ? (string) $validated['description']
                : null,
            moduleIds: array_values(array_unique($validated['module_ids'] ?? [])),
        );
    }
}
