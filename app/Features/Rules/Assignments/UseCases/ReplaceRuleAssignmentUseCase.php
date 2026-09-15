<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Actions\AssertAssignmentPlanMutableAction;
use App\Features\Rules\Assignments\Actions\AssertAssignmentProgramContextAction;
use App\Features\Rules\Assignments\Actions\AssertPublishedRuleVersionAction;
use App\Features\Rules\Assignments\Actions\PresentRuleAssignmentAction;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentInactiveException;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\ReplaceRuleAssignmentCommand;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ReplaceRuleAssignmentUseCase
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $repositoryFactory,
        private readonly AssertAssignmentPlanMutableAction $assertPlan,
        private readonly AssertAssignmentProgramContextAction $assertProgram,
        private readonly AssertPublishedRuleVersionAction $assertVersion,
        private readonly PresentRuleAssignmentAction $present,
    ) {}

    public function execute(ReplaceRuleAssignmentCommand $command): RuleAssignmentData
    {
        $this->assertPlan->assertMutable($command->planId);
        $this->assertVersion->assert($command->planId, $command->ruleId, $command->ruleVersionId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleAssignmentData {
            $current = $this->present->require(
                $repository->findByRuleAndId($command->ruleId, $command->assignmentId),
                $command->assignmentId,
            );
            if (! $current->isActive()) {
                throw RuleAssignmentInactiveException::forId($current->id);
            }

            $this->assertProgram->assertSelectedModule(
                $command->planId,
                $current->programId,
                $current->moduleId,
            );

            $now = CarbonImmutable::now('UTC')->toISOString();
            $current->withdraw($now);
            $repository->update($current, $command->lockVersion);

            $replacement = RuleAssignment::activate(
                id: (string) Str::uuid7(),
                ruleId: $current->ruleId,
                ruleVersionId: $command->ruleVersionId,
                programId: $current->programId,
                moduleId: $current->moduleId,
                now: $now,
            );
            $repository->create($replacement);

            return $this->present->toData($replacement);
        });
    }
}
