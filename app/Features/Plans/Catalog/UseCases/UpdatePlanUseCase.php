<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\AssertPlanModuleSelectionAction;
use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\UpdatePlanCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class UpdatePlanUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly AssertPlanModuleSelectionAction $assertSelection,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(UpdatePlanCommand $command): PlanDetailData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): PlanDetailData {
            $plan = $repository->findById($command->planId);
            if ($plan === null) {
                throw PlanNotFoundException::forId($command->planId);
            }

            if ($plan->lockVersion !== $command->lockVersion) {
                throw PlanConcurrencyException::forPlan($command->planId);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $changed = false;
            if ($command->hasName || $command->hasDescription) {
                $changed = $plan->updateAdministrativeFields(
                    $command->hasName ? (string) $command->name : $plan->name,
                    $command->hasDescription ? $command->description : $plan->description,
                    $now,
                ) || $changed;
            }

            if ($command->moduleIds !== null) {
                $this->assertSelection->assert($plan->moduleIds(), $command->moduleIds);
                $changed = $plan->replaceBindings(
                    $command->moduleIds,
                    static fn (): string => (string) Str::uuid7(),
                    $now,
                ) || $changed;
            }

            if ($changed) {
                $repository->update($plan, $command->lockVersion);
            }

            return $this->presentPlan->toDetail($plan);
        });
    }
}
