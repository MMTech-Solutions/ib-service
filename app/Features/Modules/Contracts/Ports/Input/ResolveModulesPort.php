<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;

interface ResolveModulesPort
{
    /**
     * @param  list<string>  $ids
     * @return list<ModuleSummaryData>
     */
    public function findByIds(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return list<ModuleSummaryData>
     */
    public function assertSelectable(array $ids): array;
}
