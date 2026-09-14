<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Models;

use App\Features\Plans\Catalog\Enums\PlanActorKind;
use App\Features\Plans\Catalog\Enums\PlanOperationalAction;

final class PlanOperationalChange
{
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public readonly PlanOperationalAction $action,
        public readonly PlanActorKind $actorKind,
        public readonly ?string $actorIamId,
        public readonly string $reason,
        public readonly bool $previousIsActive,
        public readonly bool $nextIsActive,
        public readonly ?string $causeEventId,
        public readonly ?string $causeModuleId,
        public readonly ?string $initiatingActorIamId,
        public readonly string $occurredAt,
    ) {}
}
