<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Events\V1;

final class ModuleDeactivated
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $moduleId,
        public readonly ?string $actorIamId,
        public readonly string $occurredAt,
    ) {}
}
