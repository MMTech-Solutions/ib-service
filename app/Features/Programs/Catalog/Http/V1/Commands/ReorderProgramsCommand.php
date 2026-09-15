<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\DTOs\ProgramReorderItem;
use App\Features\Programs\Catalog\Http\V1\Requests\ReorderProgramsRequest;
use Spatie\LaravelData\Data;

final class ReorderProgramsCommand extends Data
{
    /**
     * @param  list<ProgramReorderItem>  $items
     */
    public function __construct(
        public readonly string $planId,
        public readonly array $items,
    ) {}

    public static function fromRequest(ReorderProgramsRequest $request): self
    {
        $validated = $request->validated();
        $items = array_map(
            static fn (array $item): ProgramReorderItem => new ProgramReorderItem(
                id: (string) $item['id'],
                entryThreshold: (int) $item['entry_threshold'],
                lockVersion: (int) $item['lock_version'],
            ),
            array_values($validated['programs']),
        );

        return new self(
            planId: (string) $validated['plan'],
            items: $items,
        );
    }
}
