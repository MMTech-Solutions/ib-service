<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Commands;

use App\Features\Progression\Http\V1\Requests\ShowActivityEvaluationRequest;
use Spatie\LaravelData\Data;

final class ShowActivityEvaluationCommand extends Data
{
    public function __construct(
        public readonly string $activityEvaluationId,
    ) {}

    public static function fromRequest(ShowActivityEvaluationRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            activityEvaluationId: (string) $validated['activity_evaluation'],
        );
    }
}
