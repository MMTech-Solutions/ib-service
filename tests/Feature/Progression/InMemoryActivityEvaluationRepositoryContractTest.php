<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\Repositories\InMemory\InMemoryActivityEvaluationRepository;
use Illuminate\Support\Str;
use Tests\Contracts\ActivityEvaluationRepositoryContract;

final class InMemoryActivityEvaluationRepositoryContractTest extends ActivityEvaluationRepositoryContract
{
    private InMemoryActivityEvaluationRepository $repository;

    private string $moduleId;

    private string $subscriptionId;

    private string $planId;

    private string $programId;

    private string $ruleId;

    private string $ruleVersionId;

    private string $ruleAssignmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InMemoryActivityEvaluationRepository;
        $this->moduleId = (string) Str::uuid7();
        $this->subscriptionId = (string) Str::uuid7();
        $this->planId = (string) Str::uuid7();
        $this->programId = (string) Str::uuid7();
        $this->ruleId = (string) Str::uuid7();
        $this->ruleVersionId = (string) Str::uuid7();
        $this->ruleAssignmentId = (string) Str::uuid7();
    }

    protected function repository(): ActivityEvaluationRepositoryInterface
    {
        return $this->repository;
    }

    protected function moduleId(): string
    {
        return $this->moduleId;
    }

    protected function subscriptionId(): string
    {
        return $this->subscriptionId;
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function ruleId(): string
    {
        return $this->ruleId;
    }

    protected function ruleVersionId(): string
    {
        return $this->ruleVersionId;
    }

    protected function ruleAssignmentId(): string
    {
        return $this->ruleAssignmentId;
    }
}
