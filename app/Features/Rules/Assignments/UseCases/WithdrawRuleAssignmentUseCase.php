<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Actions\AssertAssignmentPlanMutableAction;
use App\Features\Rules\Assignments\Actions\AssertPublishedRuleVersionAction;
use App\Features\Rules\Assignments\Actions\PresentRuleAssignmentAction;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentInactiveException;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\WithdrawRuleAssignmentCommand;
use Carbon\CarbonImmutable;

final class WithdrawRuleAssignmentUseCase
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $repositoryFactory,
        private readonly AssertAssignmentPlanMutableAction $assertPlan,
        private readonly AssertPublishedRuleVersionAction $assertVersion,
        private readonly PresentRuleAssignmentAction $present,
    ) {}

    public function execute(WithdrawRuleAssignmentCommand $command): RuleAssignmentData
    {
        $this->assertPlan->assertMutable($command->planId);
        $this->assertVersion->rule($command->planId, $command->ruleId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleAssignmentData {
            $assignment = $this->present->require(
                $repository->findByRuleAndId($command->ruleId, $command->assignmentId),
                $command->assignmentId,
            );
            if (! $assignment->isActive()) {
                throw RuleAssignmentInactiveException::forId($assignment->id);
            }

            $assignment->withdraw(CarbonImmutable::now('UTC')->toISOString());
            $repository->update($assignment, $command->lockVersion);

            return $this->present->toData($assignment);
        });
    }
}
