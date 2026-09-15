<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\UseCases;

use App\Features\Rules\Assignments\Actions\AssertAssignmentPlanMutableAction;
use App\Features\Rules\Assignments\Actions\AssertAssignmentProgramContextAction;
use App\Features\Rules\Assignments\Actions\AssertPublishedRuleVersionAction;
use App\Features\Rules\Assignments\Actions\PresentRuleAssignmentAction;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Exceptions\DuplicateActiveRuleAssignmentException;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Assignments\Http\V1\Commands\StoreRuleAssignmentCommand;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreRuleAssignmentUseCase
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $repositoryFactory,
        private readonly AssertAssignmentPlanMutableAction $assertPlan,
        private readonly AssertAssignmentProgramContextAction $assertProgram,
        private readonly AssertPublishedRuleVersionAction $assertVersion,
        private readonly PresentRuleAssignmentAction $present,
    ) {}

    public function execute(StoreRuleAssignmentCommand $command): RuleAssignmentData
    {
        $this->assertPlan->assertMutable($command->planId);
        $this->assertProgram->assertSelectedModule($command->planId, $command->programId, $command->moduleId);
        $this->assertVersion->assert($command->planId, $command->ruleId, $command->ruleVersionId);
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): RuleAssignmentData {
            if ($repository->findActive($command->ruleId, $command->programId, $command->moduleId) !== null) {
                throw DuplicateActiveRuleAssignmentException::forContext(
                    $command->ruleId,
                    $command->programId,
                    $command->moduleId,
                );
            }

            $assignment = RuleAssignment::activate(
                id: (string) Str::uuid7(),
                ruleId: $command->ruleId,
                ruleVersionId: $command->ruleVersionId,
                programId: $command->programId,
                moduleId: $command->moduleId,
                now: CarbonImmutable::now('UTC')->toISOString(),
            );
            $repository->create($assignment);

            return $this->present->toData($assignment);
        });
    }
}
