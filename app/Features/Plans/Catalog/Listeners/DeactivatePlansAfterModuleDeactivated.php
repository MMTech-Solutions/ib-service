<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Listeners;

use App\Features\Modules\Contracts\Events\V1\ModuleDeactivated;
use App\Features\Plans\Catalog\Jobs\DeactivatePlansWithoutOperationalModulesJob;

final class DeactivatePlansAfterModuleDeactivated
{
    public function handle(ModuleDeactivated $event): void
    {
        DeactivatePlansWithoutOperationalModulesJob::dispatch(
            $event->eventId,
            $event->moduleId,
            $event->actorIamId,
        );
    }
}
