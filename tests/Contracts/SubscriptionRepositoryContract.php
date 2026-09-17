<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateOpenSubscriptionException;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateSubscriptionReplacementException;
use App\Features\Subscriptions\Catalog\Exceptions\ProgramNotOnSubscriptionPlanException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionConcurrencyException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class SubscriptionRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): SubscriptionRepositoryInterface;

    abstract protected function planId(): string;

    abstract protected function programId(): string;

    abstract protected function alternateProgramId(): string;

    abstract protected function foreignProgramId(): string;

    public function test_it_creates_pending_and_active_subscriptions_with_history(): void
    {
        $repository = $this->repository();
        $pending = $this->pendingSubscription();
        $repository->create($pending);

        $storedPending = $repository->findById($pending->id);
        self::assertNotNull($storedPending);
        self::assertSame(SubscriptionStatus::Pending, $storedPending->status);
        self::assertSame([], $storedPending->placements);
        self::assertCount(1, $storedPending->changes);
        self::assertSame(SubscriptionChangeAction::Request, $storedPending->changes[0]->action);

        $active = $this->activeSubscription();
        $repository->create($active);
        $storedActive = $repository->findById($active->id);
        self::assertNotNull($storedActive);
        self::assertSame(SubscriptionStatus::Active, $storedActive->status);
        self::assertCount(1, $storedActive->placements);
        self::assertTrue($storedActive->placements[0]->isOpen());
        self::assertFalse($storedActive->placements[0]->isFixed());
    }

    public function test_it_rejects_a_second_open_subscription_for_the_same_user(): void
    {
        $repository = $this->repository();
        $repository->create($this->pendingSubscription(externalUserId: $this->userId()));

        $this->expectException(DuplicateOpenSubscriptionException::class);
        $repository->create($this->activeSubscription(externalUserId: $this->userId()));
    }

    public function test_it_allows_a_new_open_subscription_after_rejection(): void
    {
        $repository = $this->repository();
        $pending = $this->pendingSubscription(externalUserId: $this->userId());
        $repository->create($pending);

        $writer = $repository->findById($pending->id);
        self::assertNotNull($writer);
        $writer->reject(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Incomplete KYC',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
        $repository->save($writer, 1);

        $repository->create($this->activeSubscription(externalUserId: $this->userId()));
        $open = $repository->findOpenByExternalUserId($this->userId());
        self::assertNotNull($open);
        self::assertSame(SubscriptionStatus::Active, $open->status);
    }

    public function test_it_reports_open_subscriptions_for_a_plan(): void
    {
        $repository = $this->repository();
        self::assertFalse($repository->hasOpenForPlan($this->planId()));

        $repository->create($this->pendingSubscription());
        self::assertTrue($repository->hasOpenForPlan($this->planId()));
    }

    public function test_it_rejects_replacing_the_same_subscription_twice(): void
    {
        $repository = $this->repository();
        $first = $this->activeSubscription(externalUserId: $this->userId());
        $repository->create($first);

        $outgoing = $repository->findById($first->id);
        self::assertNotNull($outgoing);
        $operationId = (string) Str::uuid7();
        $now = $this->now();
        $outgoing->endForPlanChange(
            operationId: $operationId,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $replacement = $this->replacementSubscription(
            replacesSubscriptionId: $first->id,
            operationId: $operationId,
            now: $now,
            externalUserId: $this->userId(),
        );
        $repository->replace($outgoing, 1, $replacement);

        $current = $repository->findById($replacement->id);
        self::assertNotNull($current);
        $current->cancel(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
        $repository->save($current, 1);

        $secondReplacement = $this->replacementSubscription(
            replacesSubscriptionId: $first->id,
            operationId: (string) Str::uuid7(),
            now: $this->now(),
            externalUserId: $this->userId(),
        );

        $this->expectException(DuplicateSubscriptionReplacementException::class);
        $repository->create($secondReplacement);
    }

    public function test_it_approves_rejects_cancels_and_preserves_optional_reasons(): void
    {
        $repository = $this->repository();
        $pending = $this->pendingSubscription();
        $repository->create($pending);

        $toApprove = $repository->findById($pending->id);
        self::assertNotNull($toApprove);
        $toApprove->approve(
            programId: $this->programId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Looks good',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
        $repository->save($toApprove, 1);

        $approved = $repository->findById($pending->id);
        self::assertNotNull($approved);
        self::assertSame(SubscriptionStatus::Active, $approved->status);
        self::assertSame('Looks good', $approved->changes[1]->reason);

        $toCancel = $repository->findById($pending->id);
        self::assertNotNull($toCancel);
        $toCancel->cancel(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
        $repository->save($toCancel, 2);

        $ended = $repository->findById($pending->id);
        self::assertNotNull($ended);
        self::assertSame(SubscriptionStatus::Ended, $ended->status);
        self::assertNull($ended->currentPlacement());
        self::assertNull($ended->changes[2]->reason);
    }

    public function test_reject_requires_a_non_empty_reason(): void
    {
        $this->expectException(SubscriptionInvariantException::class);

        $pending = $this->pendingSubscription();
        $pending->reject(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: '   ',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
    }

    public function test_it_rejects_programs_outside_the_subscription_plan(): void
    {
        $repository = $this->repository();

        $this->expectException(ProgramNotOnSubscriptionPlanException::class);
        $repository->create($this->activeSubscription(programId: $this->foreignProgramId()));
    }

    public function test_placement_matrix_and_contiguous_frontiers(): void
    {
        $repository = $this->repository();
        $active = $this->activeSubscription();
        $repository->create($active);

        $t1 = CarbonImmutable::parse($active->activatedAt)->utc();
        $t2 = $t1->addHour();
        $t3 = $t2->addHour();

        $writer = $repository->findById($active->id);
        self::assertNotNull($writer);
        $writer->changeProgram(
            programId: $this->alternateProgramId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $t2->toISOString(),
        );
        $repository->save($writer, 1);

        $writer = $repository->findById($active->id);
        self::assertNotNull($writer);
        $writer->fixPlacement(
            programId: $this->programId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Manual hold',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $t3->toISOString(),
        );
        $repository->save($writer, 2);

        $stored = $repository->findById($active->id);
        self::assertNotNull($stored);
        self::assertCount(3, $stored->placements);
        self::assertSame($stored->placements[0]->effectiveUntil, $stored->placements[1]->effectiveFrom);
        self::assertSame($stored->placements[1]->effectiveUntil, $stored->placements[2]->effectiveFrom);
        self::assertTrue($stored->currentPlacement()?->isFixed() ?? false);

        $atBoundary = $repository->resolvePlacementAt($active->id, $t2->toISOString());
        self::assertNotNull($atBoundary);
        self::assertSame($this->alternateProgramId(), $atBoundary->programId);

        $beforeBoundary = $repository->resolvePlacementAt(
            $active->id,
            $t2->subSecond()->toISOString(),
        );
        self::assertNotNull($beforeBoundary);
        self::assertSame($this->programId(), $beforeBoundary->programId);
    }

    public function test_release_placement_keeps_program_and_change_program_preserves_fixed_condition(): void
    {
        $repository = $this->repository();
        $active = $this->activeSubscription();
        $repository->create($active);

        $writer = $repository->findById($active->id);
        self::assertNotNull($writer);
        $fixAt = CarbonImmutable::parse($active->activatedAt)->utc()->addMinutes(10)->toISOString();
        $writer->fixPlacement(
            programId: $this->programId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $fixAt,
        );
        $repository->save($writer, 1);

        $writer = $repository->findById($active->id);
        self::assertNotNull($writer);
        $changeAt = CarbonImmutable::parse($fixAt)->utc()->addMinutes(10)->toISOString();
        $writer->changeProgram(
            programId: $this->alternateProgramId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $changeAt,
        );
        $repository->save($writer, 2);

        $fixed = $repository->findById($active->id);
        self::assertNotNull($fixed);
        self::assertSame(PlacementCondition::Fixed, $fixed->currentPlacement()?->condition);
        self::assertSame($this->alternateProgramId(), $fixed->currentPlacement()?->programId);

        $writer = $repository->findById($active->id);
        self::assertNotNull($writer);
        $releaseAt = CarbonImmutable::parse($changeAt)->utc()->addMinutes(10)->toISOString();
        $writer->releasePlacement(
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $releaseAt,
        );
        $repository->save($writer, 3);

        $released = $repository->findById($active->id);
        self::assertNotNull($released);
        self::assertSame($this->alternateProgramId(), $released->currentPlacement()?->programId);
        self::assertSame(PlacementCondition::Unfixed, $released->currentPlacement()?->condition);
    }

    public function test_plan_change_shares_operation_id_and_rolls_back_together(): void
    {
        $repository = $this->repository();
        $first = $this->activeSubscription(externalUserId: $this->userId());
        $repository->create($first);

        $outgoing = $repository->findById($first->id);
        self::assertNotNull($outgoing);
        $operationId = (string) Str::uuid7();
        $now = $this->now();
        $outgoing->endForPlanChange(
            operationId: $operationId,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Upgrade',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
        $incoming = $this->replacementSubscription(
            replacesSubscriptionId: $first->id,
            operationId: $operationId,
            now: $now,
            externalUserId: $this->userId(),
        );
        $repository->replace($outgoing, 1, $incoming);

        $ended = $repository->findById($first->id);
        $created = $repository->findById($incoming->id);
        self::assertNotNull($ended);
        self::assertNotNull($created);
        self::assertSame(SubscriptionStatus::Ended, $ended->status);
        self::assertSame(SubscriptionStatus::Active, $created->status);
        self::assertSame($operationId, $ended->changes[array_key_last($ended->changes)]->operationId);
        self::assertSame($operationId, $created->changes[0]->operationId);
        self::assertSame($first->id, $created->replacesSubscriptionId);

        $secondUserId = (string) Str::uuid7();
        $second = $this->activeSubscription(externalUserId: $secondUserId);
        $repository->create($second);
        $rollbackOutgoing = $repository->findById($second->id);
        self::assertNotNull($rollbackOutgoing);
        $rollbackOperation = (string) Str::uuid7();
        $rollbackNow = $this->now();
        $rollbackOutgoing->endForPlanChange(
            operationId: $rollbackOperation,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $rollbackNow,
        );
        $rollbackIncoming = $this->replacementSubscription(
            replacesSubscriptionId: $second->id,
            operationId: $rollbackOperation,
            now: $rollbackNow,
            externalUserId: $secondUserId,
        );

        try {
            $repository->transaction(function () use ($repository, $rollbackOutgoing, $rollbackIncoming): void {
                $repository->replace($rollbackOutgoing, 1, $rollbackIncoming);
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $restored = $repository->findById($second->id);
        self::assertNotNull($restored);
        self::assertSame(SubscriptionStatus::Active, $restored->status);
        self::assertSame(1, $restored->lockVersion);
        self::assertNull($repository->findById($rollbackIncoming->id));
    }

    public function test_it_rejects_stale_lock_versions(): void
    {
        $repository = $this->repository();
        $pending = $this->pendingSubscription();
        $repository->create($pending);

        $first = $repository->findById($pending->id);
        $stale = $repository->findById($pending->id);
        self::assertNotNull($first);
        self::assertNotNull($stale);

        $first->reject(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Duplicate',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
        $repository->save($first, 1);

        $stale->reject(
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Stale',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );

        $this->expectException(SubscriptionConcurrencyException::class);
        $repository->save($stale, 1);
    }

    public function test_history_action_matrix_rejects_invalid_snapshots(): void
    {
        $this->expectException(SubscriptionInvariantException::class);

        SubscriptionChange::record(
            id: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            subscriptionId: (string) Str::uuid7(),
            action: SubscriptionChangeAction::Approve,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            previousStatus: SubscriptionStatus::Pending,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: null,
            nextProgramId: $this->programId(),
            previousIsFixed: null,
            nextIsFixed: true,
            occurredAt: $this->now(),
        );
    }

    protected function pendingSubscription(?string $externalUserId = null): Subscription
    {
        return Subscription::requestPending(
            id: (string) Str::uuid7(),
            externalUserId: $externalUserId ?? (string) Str::uuid7(),
            planId: $this->planId(),
            requiresApproval: true,
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
    }

    protected function activeSubscription(
        ?string $externalUserId = null,
        ?string $programId = null,
    ): Subscription {
        return Subscription::requestActive(
            id: (string) Str::uuid7(),
            externalUserId: $externalUserId ?? (string) Str::uuid7(),
            planId: $this->planId(),
            programId: $programId ?? $this->programId(),
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
    }

    protected function replacementSubscription(
        string $replacesSubscriptionId,
        string $operationId,
        string $now,
        string $externalUserId,
    ): Subscription {
        return Subscription::createFromPlanChange(
            id: (string) Str::uuid7(),
            externalUserId: $externalUserId,
            planId: $this->planId(),
            replacesSubscriptionId: $replacesSubscriptionId,
            programId: $this->programId(),
            placementId: (string) Str::uuid7(),
            operationId: $operationId,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $this->actorId(),
            reason: 'Upgrade',
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
    }

    protected function userId(): string
    {
        return '01990a00-0000-7000-8000-000000000001';
    }

    protected function actorId(): string
    {
        return '01990a00-0000-7000-8000-000000000099';
    }

    protected function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
