<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Rules\Assignments\Actions\AssertPublishedRuleVersionAction;
use App\Features\Rules\Assignments\Actions\PresentRuleAssignmentAction;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\ShowRuleAssignmentCommand;

final class ShowRuleAssignmentUseCase
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $repositoryFactory,
        private readonly ResolvePlanContextPort $plans,
        private readonly AssertPublishedRuleVersionAction $assertRule,
        private readonly PresentRuleAssignmentAction $present,
    ) {}

    public function execute(ShowRuleAssignmentCommand $command): RuleAssignmentData
    {
        $this->plans->resolve(new ResolvePlanContextQueryData(plan_id: $command->planId));
        $this->assertRule->rule($command->planId, $command->ruleId);
        $assignment = $this->present->require(
            $this->repositoryFactory->make()->findByRuleAndId($command->ruleId, $command->assignmentId),
            $command->assignmentId,
        );

        return $this->present->toData($assignment);
    }
}
