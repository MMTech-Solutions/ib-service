<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentListQueryData;
use App\Features\Rules\Assignments\Exceptions\DuplicateActiveRuleAssignmentException;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentConcurrencyException;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class RuleAssignmentRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): RuleAssignmentRepositoryInterface;

    abstract protected function ruleId(): string;

    abstract protected function programId(): string;

    abstract protected function moduleId(): string;

    abstract protected function ruleVersionId(): string;

    abstract protected function alternateRuleVersionId(): string;

    public function test_it_creates_lists_and_recovers_an_assignment(): void
    {
        $repository = $this->repository();
        $assignment = $this->activeAssignment();
        $repository->create($assignment);

        $stored = $repository->findByRuleAndId($this->ruleId(), $assignment->id);
        self::assertNotNull($stored);
        self::assertTrue($stored->isActive());
        self::assertSame($this->ruleVersionId(), $stored->ruleVersionId);

        $page = $repository->paginateByRule(new RuleAssignmentListQueryData(
            ruleId: $this->ruleId(),
            page: 1,
            perPage: 10,
            active: true,
        ));
        self::assertSame(1, $page->total);
        self::assertSame($assignment->id, $page->assignments[0]->id);
        self::assertTrue($page->assignments[0]->active);
    }

    public function test_it_rejects_duplicate_active_assignments(): void
    {
        $repository = $this->repository();
        $repository->create($this->activeAssignment());

        $this->expectException(DuplicateActiveRuleAssignmentException::class);
        $repository->create($this->activeAssignment());
    }

    public function test_it_replaces_by_closing_and_creating_history(): void
    {
        $repository = $this->repository();
        $first = $this->activeAssignment();
        $repository->create($first);

        $now = $this->now();
        $writer = $repository->findById($first->id);
        self::assertNotNull($writer);
        $writer->withdraw($now);
        $repository->update($writer, 1);

        $second = RuleAssignment::activate(
            id: (string) Str::uuid7(),
            ruleId: $this->ruleId(),
            ruleVersionId: $this->alternateRuleVersionId(),
            programId: $this->programId(),
            moduleId: $this->moduleId(),
            now: $now,
        );
        $repository->create($second);

        $active = $repository->findActive($this->ruleId(), $this->programId(), $this->moduleId());
        self::assertNotNull($active);
        self::assertSame($second->id, $active->id);
        self::assertSame($this->alternateRuleVersionId(), $active->ruleVersionId);

        $page = $repository->paginateByRule(new RuleAssignmentListQueryData(
            ruleId: $this->ruleId(),
            page: 1,
            perPage: 10,
        ));
        self::assertSame(2, $page->total);
        self::assertFalse(
            collect($page->assignments)->firstWhere('id', $first->id)?->active ?? true,
        );
    }

    public function test_it_rejects_stale_lock_versions(): void
    {
        $repository = $this->repository();
        $assignment = $this->activeAssignment();
        $repository->create($assignment);

        $writer = $repository->findById($assignment->id);
        $stale = $repository->findById($assignment->id);
        self::assertNotNull($writer);
        self::assertNotNull($stale);

        $writer->withdraw($this->now());
        $repository->update($writer, 1);

        $stale->withdraw($this->now());
        $this->expectException(RuleAssignmentConcurrencyException::class);
        $repository->update($stale, 1);
    }

    public function test_transaction_rolls_back_after_an_error(): void
    {
        $repository = $this->repository();
        $assignment = $this->activeAssignment();
        $repository->create($assignment);

        try {
            $repository->transaction(function () use ($repository, $assignment): void {
                $writer = $repository->findById($assignment->id);
                self::assertNotNull($writer);
                $writer->withdraw($this->now());
                $repository->update($writer, 1);
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $stored = $repository->findById($assignment->id);
        self::assertNotNull($stored);
        self::assertTrue($stored->isActive());
        self::assertSame(1, $stored->lockVersion);
    }

    protected function activeAssignment(?string $ruleVersionId = null): RuleAssignment
    {
        return RuleAssignment::activate(
            id: (string) Str::uuid7(),
            ruleId: $this->ruleId(),
            ruleVersionId: $ruleVersionId ?? $this->ruleVersionId(),
            programId: $this->programId(),
            moduleId: $this->moduleId(),
            now: $this->now(),
        );
    }

    protected function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
