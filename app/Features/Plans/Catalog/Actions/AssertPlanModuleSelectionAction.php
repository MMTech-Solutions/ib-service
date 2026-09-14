<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Actions;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;

final class AssertPlanModuleSelectionAction
{
    public function __construct(private readonly ResolveModulesPort $modules) {}

    /**
     * @param  list<string>  $currentModuleIds
     * @param  list<string>  $incomingModuleIds
     */
    public function assert(array $currentModuleIds, array $incomingModuleIds): void
    {
        $added = array_values(array_diff($incomingModuleIds, $currentModuleIds));
        if ($added !== []) {
            $this->modules->assertSelectable($added);
        }
    }
}
