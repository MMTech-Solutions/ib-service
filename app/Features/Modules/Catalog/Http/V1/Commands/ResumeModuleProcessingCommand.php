<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\Http\V1\Requests\ResumeModuleProcessingRequest;
use App\SharedFeatures\User\Context\UserContext;
use Spatie\LaravelData\Data;

final class ResumeModuleProcessingCommand extends Data
{
    public function __construct(
        public readonly string $moduleId,
        public readonly int $lockVersion,
        public readonly string $actorIamId,
        public readonly string $reason,
    ) {}

    public static function fromRequest(ResumeModuleProcessingRequest $request, UserContext $userContext): self
    {
        $validated = $request->validated();

        return new self(
            moduleId: (string) $validated['module'],
            lockVersion: (int) $validated['lock_version'],
            actorIamId: $userContext->id(),
            reason: trim((string) $validated['reason']),
        );
    }
}
