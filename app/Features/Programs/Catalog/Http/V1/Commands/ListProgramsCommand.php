<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\Http\V1\Requests\ListProgramsRequest;
use Spatie\LaravelData\Data;

final class ListProgramsCommand extends Data
{
    public function __construct(
        public readonly string $planId,
    ) {}

    public static function fromRequest(ListProgramsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
        );
    }
}
