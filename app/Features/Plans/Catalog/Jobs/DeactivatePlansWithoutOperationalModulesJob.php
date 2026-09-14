<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Jobs;

use App\Features\Plans\Catalog\UseCases\DeactivatePlansWithoutOperationalModulesUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeactivatePlansWithoutOperationalModulesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public readonly ?string $eventId = null,
        public readonly ?string $moduleId = null,
        public readonly ?string $initiatingActorIamId = null,
    ) {}

    public function handle(DeactivatePlansWithoutOperationalModulesUseCase $useCase): void
    {
        $useCase->execute(
            eventId: $this->eventId,
            causeModuleId: $this->moduleId,
            initiatingActorIamId: $this->initiatingActorIamId,
        );
    }
}
