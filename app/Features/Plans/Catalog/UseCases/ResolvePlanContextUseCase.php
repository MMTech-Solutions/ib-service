<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\PlanContextData;
use App\Features\Plans\Contracts\Exceptions\ModuleNotEnabledOnPlanException;
use App\Features\Plans\Contracts\Exceptions\PlanArchivedException;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;

final class ResolvePlanContextUseCase implements ResolvePlanContextPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function resolve(string $planId): PlanContextData
    {
        $plan = $this->repositoryFactory->make()->findByIdIncludingArchived($planId);
        if ($plan === null) {
            throw PlanNotFoundException::forId($planId);
        }

        return new PlanContextData(
            id: $plan->id,
            archived: $plan->deletedAt !== null,
            enabled_module_ids: $plan->moduleIds(),
        );
    }

    public function assertEnabledModuleIds(string $planId, array $moduleIds): PlanContextData
    {
        $context = $this->resolve($planId);
        if ($context->archived) {
            throw PlanArchivedException::forId($planId);
        }

        $missing = array_values(array_diff($moduleIds, $context->enabled_module_ids));
        if ($missing !== []) {
            throw ModuleNotEnabledOnPlanException::forIds($missing);
        }

        return $context;
    }
}
