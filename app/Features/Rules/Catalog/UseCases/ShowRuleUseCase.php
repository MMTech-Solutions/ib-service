<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Catalog\Actions\PresentRuleAction;
use App\Features\Rules\Catalog\DTOs\RuleDetailData;
use App\Features\Rules\Catalog\Exceptions\RuleNotFoundException;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Http\V1\Commands\ShowRuleCommand;

final class ShowRuleUseCase
{
    public function __construct(
        private readonly RuleRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
        private readonly PresentRuleAction $presentRule,
    ) {}

    public function execute(ShowRuleCommand $command): RuleDetailData
    {
        $this->plans->resolve(new ResolvePlanContextQueryData(plan_id: $command->planId));
        $rule = $this->repositoryFactory->make()->findByPlanAndId($command->planId, $command->ruleId);
        if ($rule === null) {
            throw RuleNotFoundException::forId($command->ruleId);
        }

        return $this->presentRule->toDetail($rule);
    }
}
