<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Actions;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\Models\Plan;

final class PresentPlanAction
{
    public function __construct(private readonly ResolveModulesPort $modules) {}

    public function toDetail(Plan $plan): PlanDetailData
    {
        return $plan->toDetailData($this->index($plan->moduleIds()));
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, ModuleSummaryData>
     */
    public function index(array $ids): array
    {
        $indexed = [];
        foreach ($this->modules->findByIds($ids) as $module) {
            $indexed[$module->id] = $module;
        }

        return $indexed;
    }
}
