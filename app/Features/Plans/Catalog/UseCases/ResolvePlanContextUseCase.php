<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Data\V1\PlanContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Exceptions\ModuleNotEnabledOnPlanException;
use App\Features\Plans\Contracts\Exceptions\PlanArchivedException;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;

final class ResolvePlanContextUseCase implements ResolvePlanContextPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolvePlanContextQueryData $query): PlanContextData
    {
        $plan = $this->repositoryFactory->make()->findByIdIncludingArchived($query->plan_id);
        if ($plan === null) {
            throw PlanNotFoundException::forId($query->plan_id);
        }

        return new PlanContextData(
            id: $plan->id,
            archived: $plan->deletedAt !== null,
            enabled_module_ids: $plan->moduleIds(),
        );
    }

    public function assertEnabledModuleIds(AssertEnabledModuleIdsQueryData $query): PlanContextData
    {
        $context = $this->resolve(new ResolvePlanContextQueryData(plan_id: $query->plan_id));
        if ($context->archived) {
            throw PlanArchivedException::forId($query->plan_id);
        }

        $missing = array_values(array_diff($query->module_ids, $context->enabled_module_ids));
        if ($missing !== []) {
            throw ModuleNotEnabledOnPlanException::forIds($missing);
        }

        return $context;
    }
}
