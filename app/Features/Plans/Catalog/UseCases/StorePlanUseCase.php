<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\AssertPlanModuleSelectionAction;
use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\Exceptions\DuplicatePlanCodeConflictException;
use App\Features\Plans\Catalog\Exceptions\DuplicatePlanCodeException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\StorePlanCommand;
use App\Features\Plans\Catalog\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StorePlanUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly AssertPlanModuleSelectionAction $assertSelection,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(StorePlanCommand $command): PlanDetailData
    {
        $this->assertSelection->assert([], $command->moduleIds);
        $repository = $this->repositoryFactory->make();
        $plan = Plan::create(
            id: (string) Str::uuid7(),
            code: $command->code,
            name: $command->name,
            description: $command->description,
            moduleIds: $command->moduleIds,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: CarbonImmutable::now('UTC')->toISOString(),
            requiresApproval: $command->requiresApproval,
        );

        try {
            $repository->create($plan);
        } catch (DuplicatePlanCodeException $exception) {
            throw DuplicatePlanCodeConflictException::forCode($command->code);
        }

        return $this->presentPlan->toDetail($plan);
    }
}
