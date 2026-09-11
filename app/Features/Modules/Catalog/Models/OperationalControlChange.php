<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Models;

use App\Features\Modules\Catalog\DTOs\ModuleOperationalChangeData;
use App\Features\Modules\Catalog\Enums\OperationalControlAction;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;

final class OperationalControlChange
{
    public function __construct(
        public readonly string $id,
        public readonly string $moduleId,
        public readonly OperationalControlAction $action,
        public readonly string $actorIamId,
        public readonly string $reason,
        public readonly bool $previousIsActive,
        public readonly ProcessingStatus $previousProcessingStatus,
        public readonly bool $nextIsActive,
        public readonly ProcessingStatus $nextProcessingStatus,
        public readonly string $occurredAt,
    ) {}

    public function toData(): ModuleOperationalChangeData
    {
        return new ModuleOperationalChangeData(
            id: $this->id,
            module_id: $this->moduleId,
            action: $this->action->value,
            actor_iam_id: $this->actorIamId,
            reason: $this->reason,
            previous_is_active: $this->previousIsActive,
            previous_processing_status: $this->previousProcessingStatus->value,
            next_is_active: $this->nextIsActive,
            next_processing_status: $this->nextProcessingStatus->value,
            occurred_at: $this->occurredAt,
        );
    }
}
