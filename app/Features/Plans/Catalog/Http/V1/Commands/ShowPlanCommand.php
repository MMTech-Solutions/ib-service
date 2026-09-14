<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\Http\V1\Requests\ShowPlanRequest;
use Spatie\LaravelData\Data;

final class ShowPlanCommand extends Data
{
    public function __construct(public readonly string $planId) {}

    public static function fromRequest(ShowPlanRequest $request): self
    {
        return new self(planId: (string) $request->validated('plan'));
    }
}
