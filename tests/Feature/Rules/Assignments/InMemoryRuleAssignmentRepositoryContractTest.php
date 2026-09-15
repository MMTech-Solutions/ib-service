<?php

declare(strict_types=1);

namespace Tests\Feature\Rules\Assignments;

use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\Repositories\InMemory\InMemoryRuleAssignmentRepository;
use Illuminate\Support\Str;
use Tests\Contracts\RuleAssignmentRepositoryContract;

final class InMemoryRuleAssignmentRepositoryContractTest extends RuleAssignmentRepositoryContract
{
    private InMemoryRuleAssignmentRepository $repository;

    private string $ruleId;

    private string $programId;

    private string $moduleId;

    private string $ruleVersionId;

    private string $alternateRuleVersionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryRuleAssignmentRepository;
        $this->ruleId = (string) Str::uuid7();
        $this->programId = (string) Str::uuid7();
        $this->moduleId = (string) Str::uuid7();
        $this->ruleVersionId = (string) Str::uuid7();
        $this->alternateRuleVersionId = (string) Str::uuid7();
    }

    protected function repository(): RuleAssignmentRepositoryInterface
    {
        return $this->repository;
    }

    protected function ruleId(): string
    {
        return $this->ruleId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function moduleId(): string
    {
        return $this->moduleId;
    }

    protected function ruleVersionId(): string
    {
        return $this->ruleVersionId;
    }

    protected function alternateRuleVersionId(): string
    {
        return $this->alternateRuleVersionId;
    }
}
