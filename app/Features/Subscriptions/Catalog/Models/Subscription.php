<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Models;

use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionOrigin;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;
use Carbon\CarbonImmutable;
use Closure;

final class Subscription
{
    /**
     * @param  list<SubscriptionPlacement>  $placements
     * @param  list<SubscriptionChange>  $changes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $externalUserId,
        public readonly string $planId,
        public readonly SubscriptionOrigin $origin,
        public readonly ?bool $requiresApproval,
        public SubscriptionStatus $status,
        public ?string $activatedAt,
        public ?string $closedAt,
        public readonly ?string $replacesSubscriptionId,
        public int $lockVersion,
        public array $placements,
        public array $changes,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {
        $this->assertRootInvariants();
    }

    public static function requestPending(
        string $id,
        string $externalUserId,
        string $planId,
        bool $requiresApproval,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): self {
        $subscription = new self(
            id: $id,
            externalUserId: $externalUserId,
            planId: $planId,
            origin: SubscriptionOrigin::UserApplication,
            requiresApproval: $requiresApproval,
            status: SubscriptionStatus::Pending,
            activatedAt: null,
            closedAt: null,
            replacesSubscriptionId: null,
            lockVersion: 1,
            placements: [],
            changes: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $subscription->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::Request,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: null,
            nextStatus: SubscriptionStatus::Pending,
            previousProgramId: null,
            nextProgramId: null,
            previousIsFixed: null,
            nextIsFixed: null,
            occurredAt: $now,
        );

        return $subscription;
    }

    public static function requestActive(
        string $id,
        string $externalUserId,
        string $planId,
        string $programId,
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): self {
        $subscription = new self(
            id: $id,
            externalUserId: $externalUserId,
            planId: $planId,
            origin: SubscriptionOrigin::UserApplication,
            requiresApproval: false,
            status: SubscriptionStatus::Active,
            activatedAt: $now,
            closedAt: null,
            replacesSubscriptionId: null,
            lockVersion: 1,
            placements: [
                SubscriptionPlacement::open(
                    id: $placementId,
                    subscriptionId: $id,
                    programId: $programId,
                    condition: PlacementCondition::Unfixed,
                    effectiveFrom: $now,
                ),
            ],
            changes: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $subscription->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::Request,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: null,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: null,
            nextProgramId: $programId,
            previousIsFixed: null,
            nextIsFixed: false,
            occurredAt: $now,
        );

        return $subscription;
    }

    public static function createFromPlanChange(
        string $id,
        string $externalUserId,
        string $planId,
        string $replacesSubscriptionId,
        string $programId,
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): self {
        $subscription = new self(
            id: $id,
            externalUserId: $externalUserId,
            planId: $planId,
            origin: SubscriptionOrigin::AdminPlanChange,
            requiresApproval: null,
            status: SubscriptionStatus::Active,
            activatedAt: $now,
            closedAt: null,
            replacesSubscriptionId: $replacesSubscriptionId,
            lockVersion: 1,
            placements: [
                SubscriptionPlacement::open(
                    id: $placementId,
                    subscriptionId: $id,
                    programId: $programId,
                    condition: PlacementCondition::Unfixed,
                    effectiveFrom: $now,
                ),
            ],
            changes: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $subscription->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::ChangePlanIn,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: null,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: null,
            nextProgramId: $programId,
            previousIsFixed: null,
            nextIsFixed: false,
            occurredAt: $now,
        );

        return $subscription;
    }

    public function currentPlacement(): ?SubscriptionPlacement
    {
        foreach ($this->placements as $placement) {
            if ($placement->isOpen()) {
                return $placement;
            }
        }

        return null;
    }

    public function placementAt(string $occurredAt): ?SubscriptionPlacement
    {
        $matches = array_values(array_filter(
            $this->placements,
            static fn (SubscriptionPlacement $placement): bool => $placement->covers($occurredAt),
        ));

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            static function (SubscriptionPlacement $left, SubscriptionPlacement $right): int {
                $fromCompare = strcmp($left->effectiveFrom, $right->effectiveFrom);
                if ($fromCompare !== 0) {
                    return $fromCompare;
                }

                return strcmp($left->id, $right->id);
            },
        );

        return $matches[array_key_last($matches)];
    }

    public function approve(
        string $programId,
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Pending);

        $this->status = SubscriptionStatus::Active;
        $this->activatedAt = $now;
        $this->updatedAt = $now;
        $this->placements[] = SubscriptionPlacement::open(
            id: $placementId,
            subscriptionId: $this->id,
            programId: $programId,
            condition: PlacementCondition::Unfixed,
            effectiveFrom: $now,
        );

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::Approve,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Pending,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: null,
            nextProgramId: $programId,
            previousIsFixed: null,
            nextIsFixed: false,
            occurredAt: $now,
        );
    }

    public function reject(
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Pending);

        $this->status = SubscriptionStatus::Rejected;
        $this->closedAt = $now;
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::Reject,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Pending,
            nextStatus: SubscriptionStatus::Rejected,
            previousProgramId: null,
            nextProgramId: null,
            previousIsFixed: null,
            nextIsFixed: null,
            occurredAt: $now,
        );
    }

    public function cancel(
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Active);
        $current = $this->requireOpenPlacement();
        $current->close($now);

        $this->status = SubscriptionStatus::Ended;
        $this->closedAt = $now;
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::Cancel,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Active,
            nextStatus: SubscriptionStatus::Ended,
            previousProgramId: $current->programId,
            nextProgramId: $current->programId,
            previousIsFixed: $current->isFixed(),
            nextIsFixed: $current->isFixed(),
            occurredAt: $now,
        );
    }

    public function endForPlanChange(
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Active);
        $current = $this->requireOpenPlacement();
        $current->close($now);

        $this->status = SubscriptionStatus::Ended;
        $this->closedAt = $now;
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::ChangePlanOut,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Active,
            nextStatus: SubscriptionStatus::Ended,
            previousProgramId: $current->programId,
            nextProgramId: null,
            previousIsFixed: $current->isFixed(),
            nextIsFixed: null,
            occurredAt: $now,
        );
    }

    public function changeProgram(
        string $programId,
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Active);
        $current = $this->requireOpenPlacement();
        $condition = $current->condition;
        $current->close($now);

        $this->placements[] = SubscriptionPlacement::open(
            id: $placementId,
            subscriptionId: $this->id,
            programId: $programId,
            condition: $condition,
            effectiveFrom: $now,
        );
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::ChangeProgram,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Active,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: $current->programId,
            nextProgramId: $programId,
            previousIsFixed: $condition->isFixed(),
            nextIsFixed: $condition->isFixed(),
            occurredAt: $now,
        );
    }

    public function fixPlacement(
        string $programId,
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Active);
        $current = $this->requireOpenPlacement();
        $current->close($now);

        $this->placements[] = SubscriptionPlacement::open(
            id: $placementId,
            subscriptionId: $this->id,
            programId: $programId,
            condition: PlacementCondition::Fixed,
            effectiveFrom: $now,
        );
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::FixPlacement,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Active,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: $current->programId,
            nextProgramId: $programId,
            previousIsFixed: $current->isFixed(),
            nextIsFixed: true,
            occurredAt: $now,
        );
    }

    public function releasePlacement(
        string $placementId,
        string $operationId,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        Closure $generateId,
        string $now,
    ): void {
        $this->assertMutableOpen(SubscriptionStatus::Active);
        $current = $this->requireOpenPlacement();

        if (! $current->isFixed()) {
            throw SubscriptionInvariantException::withMessage(
                "Subscription [{$this->id}] placement is not fixed.",
            );
        }

        $current->close($now);
        $this->placements[] = SubscriptionPlacement::open(
            id: $placementId,
            subscriptionId: $this->id,
            programId: $current->programId,
            condition: PlacementCondition::Unfixed,
            effectiveFrom: $now,
        );
        $this->updatedAt = $now;

        $this->recordChange(
            id: $generateId(),
            operationId: $operationId,
            action: SubscriptionChangeAction::ReleasePlacement,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: SubscriptionStatus::Active,
            nextStatus: SubscriptionStatus::Active,
            previousProgramId: $current->programId,
            nextProgramId: $current->programId,
            previousIsFixed: true,
            nextIsFixed: false,
            occurredAt: $now,
        );
    }

    private function recordChange(
        string $id,
        string $operationId,
        SubscriptionChangeAction $action,
        SubscriptionActorKind $actorKind,
        ?string $actorExternalUserId,
        ?string $reason,
        ?SubscriptionStatus $previousStatus,
        ?SubscriptionStatus $nextStatus,
        ?string $previousProgramId,
        ?string $nextProgramId,
        ?bool $previousIsFixed,
        ?bool $nextIsFixed,
        string $occurredAt,
    ): void {
        $this->changes[] = SubscriptionChange::record(
            id: $id,
            operationId: $operationId,
            subscriptionId: $this->id,
            action: $action,
            actorKind: $actorKind,
            actorExternalUserId: $actorExternalUserId,
            reason: $reason,
            previousStatus: $previousStatus,
            nextStatus: $nextStatus,
            previousProgramId: $previousProgramId,
            nextProgramId: $nextProgramId,
            previousIsFixed: $previousIsFixed,
            nextIsFixed: $nextIsFixed,
            occurredAt: $occurredAt,
        );
    }

    private function assertMutableOpen(SubscriptionStatus $expected): void
    {
        if ($this->status->isTerminal()) {
            throw SubscriptionInvariantException::withMessage(
                "Subscription [{$this->id}] is terminal and immutable.",
            );
        }

        if ($this->status !== $expected) {
            throw SubscriptionInvariantException::withMessage(
                "Subscription [{$this->id}] status must be {$expected->value}.",
            );
        }
    }

    private function requireOpenPlacement(): SubscriptionPlacement
    {
        $current = $this->currentPlacement();
        if ($current === null) {
            throw SubscriptionInvariantException::withMessage(
                "Subscription [{$this->id}] has no open placement.",
            );
        }

        return $current;
    }

    private function assertRootInvariants(): void
    {
        if ($this->lockVersion <= 0) {
            throw SubscriptionInvariantException::withMessage('lock_version must be greater than zero.');
        }

        if ($this->origin === SubscriptionOrigin::UserApplication) {
            if ($this->requiresApproval === null) {
                throw SubscriptionInvariantException::withMessage(
                    'User applications require an approval snapshot.',
                );
            }

            if ($this->replacesSubscriptionId !== null) {
                throw SubscriptionInvariantException::withMessage(
                    'User applications cannot replace another subscription.',
                );
            }
        }

        if ($this->origin === SubscriptionOrigin::AdminPlanChange) {
            if ($this->requiresApproval !== null) {
                throw SubscriptionInvariantException::withMessage(
                    'Admin plan changes cannot carry an approval snapshot.',
                );
            }

            if ($this->replacesSubscriptionId === null) {
                throw SubscriptionInvariantException::withMessage(
                    'Admin plan changes must replace a previous subscription.',
                );
            }
        }

        if ($this->replacesSubscriptionId === $this->id) {
            throw SubscriptionInvariantException::withMessage(
                'A subscription cannot replace itself.',
            );
        }

        match ($this->status) {
            SubscriptionStatus::Pending => $this->assertPendingShape(),
            SubscriptionStatus::Active => $this->assertActiveShape(),
            SubscriptionStatus::Rejected => $this->assertRejectedShape(),
            SubscriptionStatus::Ended => $this->assertEndedShape(),
        };

        $this->assertTimestampOrder();
        $this->assertPlacementHistory();
    }

    private function assertPendingShape(): void
    {
        if ($this->activatedAt !== null || $this->closedAt !== null || $this->placements !== []) {
            throw SubscriptionInvariantException::withMessage(
                'Pending subscriptions cannot be activated, closed, or placed.',
            );
        }
    }

    private function assertActiveShape(): void
    {
        if ($this->activatedAt === null || $this->closedAt !== null) {
            throw SubscriptionInvariantException::withMessage(
                'Active subscriptions require activated_at and no closed_at.',
            );
        }

        $open = array_values(array_filter(
            $this->placements,
            static fn (SubscriptionPlacement $placement): bool => $placement->isOpen(),
        ));

        if (count($open) !== 1) {
            throw SubscriptionInvariantException::withMessage(
                'Active subscriptions require exactly one open placement.',
            );
        }
    }

    private function assertRejectedShape(): void
    {
        if ($this->activatedAt !== null || $this->closedAt === null || $this->placements !== []) {
            throw SubscriptionInvariantException::withMessage(
                'Rejected subscriptions require closed_at without activation or placement.',
            );
        }
    }

    private function assertEndedShape(): void
    {
        if ($this->activatedAt === null || $this->closedAt === null) {
            throw SubscriptionInvariantException::withMessage(
                'Ended subscriptions require activated_at and closed_at.',
            );
        }

        foreach ($this->placements as $placement) {
            if ($placement->isOpen()) {
                throw SubscriptionInvariantException::withMessage(
                    'Ended subscriptions cannot keep an open placement.',
                );
            }
        }
    }

    private function assertTimestampOrder(): void
    {
        $createdAt = CarbonImmutable::parse($this->createdAt)->utc();

        if ($this->activatedAt !== null) {
            $activatedAt = CarbonImmutable::parse($this->activatedAt)->utc();
            if ($activatedAt->lt($createdAt)) {
                throw SubscriptionInvariantException::withMessage(
                    'activated_at cannot precede created_at.',
                );
            }
        }

        if ($this->closedAt !== null) {
            $closedAt = CarbonImmutable::parse($this->closedAt)->utc();
            if ($closedAt->lt($createdAt)) {
                throw SubscriptionInvariantException::withMessage(
                    'closed_at cannot precede created_at.',
                );
            }

            if ($this->activatedAt !== null) {
                $activatedAt = CarbonImmutable::parse($this->activatedAt)->utc();
                if ($closedAt->lt($activatedAt)) {
                    throw SubscriptionInvariantException::withMessage(
                        'closed_at cannot precede activated_at.',
                    );
                }
            }
        }
    }

    private function assertPlacementHistory(): void
    {
        $ordered = $this->placements;
        usort(
            $ordered,
            static function (SubscriptionPlacement $left, SubscriptionPlacement $right): int {
                $fromCompare = strcmp($left->effectiveFrom, $right->effectiveFrom);
                if ($fromCompare !== 0) {
                    return $fromCompare;
                }

                return strcmp($left->id, $right->id);
            },
        );

        $previous = null;
        foreach ($ordered as $placement) {
            if ($previous !== null) {
                if ($previous->effectiveUntil === null) {
                    throw SubscriptionInvariantException::withMessage(
                        'Only the latest placement may remain open.',
                    );
                }

                if ($previous->effectiveUntil !== $placement->effectiveFrom) {
                    throw SubscriptionInvariantException::withMessage(
                        'Placement intervals must be contiguous.',
                    );
                }
            }

            $previous = $placement;
        }
    }
}
