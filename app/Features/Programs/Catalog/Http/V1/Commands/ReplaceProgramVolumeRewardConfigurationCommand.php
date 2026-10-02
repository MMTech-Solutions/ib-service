<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Commands;

use App\Features\Programs\Catalog\Http\V1\Requests\ReplaceProgramVolumeRewardConfigurationRequest;
use Spatie\LaravelData\Data;

final class ReplaceProgramVolumeRewardConfigurationCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $programId,
        public readonly string $mode,
    ) {}

    public static function fromRequest(ReplaceProgramVolumeRewardConfigurationRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            programId: (string) $validated['program'],
            mode: (string) ($validated['mode'] ?? 'periodic'),
        );
    }
}
