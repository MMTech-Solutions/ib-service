<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Assignments\Actions\AssertPublishedRuleVersionAction;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentsPageData;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\ListRuleAssignmentsCommand;

final class ListRuleAssignmentsUseCase
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
        private readonly AssertPublishedRuleVersionAction $assertRule,
    ) {}

    public function execute(ListRuleAssignmentsCommand $command): RuleAssignmentsPageData
    {
        $this->plans->resolve(new ResolvePlanContextQueryData(plan_id: $command->planId));
        $this->assertRule->rule($command->planId, $command->ruleId);

        return $this->repositoryFactory->make()->paginateByRule($command->toQueryData());
    }
}
