<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\Http\V1\Requests\ReorderProgramsRequest;
use Spatie\LaravelData\Data;

final class ReorderProgramsCommand extends Data
{
    /**
     * @param  list<string>  $programIds
     */
    public function __construct(
        public readonly string $planId,
        public readonly array $programIds,
    ) {}

    public static function fromRequest(ReorderProgramsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            programIds: array_values($validated['program_ids']),
        );
    }
}
