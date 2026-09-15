<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Catalog\DTOs\RuleData;
use App\Features\Rules\Catalog\DTOs\RulesPageData;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\ListRulesCommand;
use App\Features\Rules\Catalog\Models\Rule;

final class ListRulesUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
    ) {}

    public function execute(ListRulesCommand $command): RulesPageData
    {
        $this->plans->resolve($command->planId);
        $rules = $this->repositoryFactory->make()->listByPlanId($command->planId);

        return new RulesPageData(
            rules: array_map(
                static fn (Rule $rule): RuleData => $rule->toData(),
                $rules,
            ),
        );
    }
}
