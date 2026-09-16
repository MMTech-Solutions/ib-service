<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Models;

use App\Features\Subscriptions\Catalog\Enums\PlacementCondition;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;

final class SubscriptionChange
{
    public function __construct(
        public readonly string $id,
        public readonly string $operationId,
        public readonly string $subscriptionId,
        public readonly SubscriptionChangeAction $action,
        public readonly SubscriptionActorKind $actorKind,
        public readonly ?string $actorExternalUserId,
        public readonly ?string $reason,
        public readonly ?SubscriptionStatus $previousStatus,
        public readonly ?SubscriptionStatus $nextStatus,
        public readonly ?string $previousProgramId,
        public readonly ?string $nextProgramId,
        public readonly ?bool $previousIsFixed,
        public readonly ?bool $nextIsFixed,
        public readonly string $occurredAt,
    ) {
        $this->assertValid();
    }

    public static function record(
        string $id,
        string $operationId,
        string $subscriptionId,
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
    ): self {
        return new self(
            id: $id,
            operationId: $operationId,
            subscriptionId: $subscriptionId,
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

    public static function fromPlacementCondition(?PlacementCondition $condition): ?bool
    {
        return $condition?->isFixed();
    }

    private function assertValid(): void
    {
        if ($this->actorKind === SubscriptionActorKind::Iam && ($this->actorExternalUserId === null || $this->actorExternalUserId === '')) {
            throw SubscriptionInvariantException::withMessage(
                'IAM actors require actor_external_user_id.',
            );
        }

        if ($this->actorKind === SubscriptionActorKind::System && $this->actorExternalUserId !== null) {
            throw SubscriptionInvariantException::withMessage(
                'System actors cannot carry actor_external_user_id.',
            );
        }

        if ($this->action === SubscriptionChangeAction::Reject) {
            if ($this->reason === null || trim($this->reason) === '') {
                throw SubscriptionInvariantException::withMessage(
                    'Reject actions require a non-empty reason.',
                );
            }
        }

        $this->assertPlacementSnapshotPair(
            $this->previousProgramId,
            $this->previousIsFixed,
            'previous',
        );
        $this->assertPlacementSnapshotPair(
            $this->nextProgramId,
            $this->nextIsFixed,
            'next',
        );

        match ($this->action) {
            SubscriptionChangeAction::Request => $this->assertRequestMatrix(),
            SubscriptionChangeAction::Approve => $this->assertApproveMatrix(),
            SubscriptionChangeAction::Reject => $this->assertRejectMatrix(),
            SubscriptionChangeAction::Cancel => $this->assertCancelMatrix(),
            SubscriptionChangeAction::ChangePlanOut => $this->assertChangePlanOutMatrix(),
            SubscriptionChangeAction::ChangePlanIn => $this->assertChangePlanInMatrix(),
            SubscriptionChangeAction::ChangeProgram => $this->assertChangeProgramMatrix(),
            SubscriptionChangeAction::FixPlacement => $this->assertFixPlacementMatrix(),
            SubscriptionChangeAction::ReleasePlacement => $this->assertReleasePlacementMatrix(),
        };
    }

    private function assertPlacementSnapshotPair(?string $programId, ?bool $isFixed, string $side): void
    {
        $programPresent = $programId !== null;
        $fixedPresent = $isFixed !== null;

        if ($programPresent !== $fixedPresent) {
            throw SubscriptionInvariantException::withMessage(
                "Placement snapshot [{$side}] requires program and is_fixed together.",
            );
        }
    }

    private function assertRequestMatrix(): void
    {
        if ($this->previousStatus !== null) {
            throw SubscriptionInvariantException::withMessage('Request cannot have previous_status.');
        }

        if ($this->nextStatus === SubscriptionStatus::Pending) {
            if ($this->nextProgramId !== null || $this->nextIsFixed !== null) {
                throw SubscriptionInvariantException::withMessage(
                    'Pending request cannot include placement snapshot.',
                );
            }

            return;
        }

        if ($this->nextStatus === SubscriptionStatus::Active) {
            if ($this->nextProgramId === null || $this->nextIsFixed !== false) {
                throw SubscriptionInvariantException::withMessage(
                    'Automatic activation requires unfixed next placement.',
                );
            }

            return;
        }

        throw SubscriptionInvariantException::withMessage(
            'Request next_status must be pending or active.',
        );
    }

    private function assertApproveMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Pending
            || $this->nextStatus !== SubscriptionStatus::Active
            || $this->previousProgramId !== null
            || $this->nextProgramId === null
            || $this->nextIsFixed !== false) {
            throw SubscriptionInvariantException::withMessage(
                'Approve must transition pending to active with unfixed placement.',
            );
        }
    }

    private function assertRejectMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Pending
            || $this->nextStatus !== SubscriptionStatus::Rejected
            || $this->previousProgramId !== null
            || $this->nextProgramId !== null) {
            throw SubscriptionInvariantException::withMessage(
                'Reject must transition pending to rejected without placement.',
            );
        }
    }

    private function assertCancelMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Active
            || $this->nextStatus !== SubscriptionStatus::Ended
            || $this->previousProgramId === null
            || $this->nextProgramId !== $this->previousProgramId
            || $this->previousIsFixed === null
            || $this->nextIsFixed !== $this->previousIsFixed) {
            throw SubscriptionInvariantException::withMessage(
                'Cancel must end an active subscription preserving placement snapshot.',
            );
        }
    }

    private function assertChangePlanOutMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Active
            || $this->nextStatus !== SubscriptionStatus::Ended
            || $this->previousProgramId === null
            || $this->nextProgramId !== null
            || $this->previousIsFixed === null
            || $this->nextIsFixed !== null) {
            throw SubscriptionInvariantException::withMessage(
                'Change plan out must end the previous subscription and clear next placement.',
            );
        }
    }

    private function assertChangePlanInMatrix(): void
    {
        if ($this->previousStatus !== null
            || $this->nextStatus !== SubscriptionStatus::Active
            || $this->previousProgramId !== null
            || $this->nextProgramId === null
            || $this->nextIsFixed !== false) {
            throw SubscriptionInvariantException::withMessage(
                'Change plan in must create an active unfixed subscription.',
            );
        }
    }

    private function assertChangeProgramMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Active
            || $this->nextStatus !== SubscriptionStatus::Active
            || $this->previousProgramId === null
            || $this->nextProgramId === null
            || $this->previousIsFixed === null
            || $this->nextIsFixed !== $this->previousIsFixed) {
            throw SubscriptionInvariantException::withMessage(
                'Change program must preserve placement condition on an active subscription.',
            );
        }
    }

    private function assertFixPlacementMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Active
            || $this->nextStatus !== SubscriptionStatus::Active
            || $this->previousProgramId === null
            || $this->nextProgramId === null
            || $this->nextIsFixed !== true) {
            throw SubscriptionInvariantException::withMessage(
                'Fix placement must leave an active subscription fixed.',
            );
        }
    }

    private function assertReleasePlacementMatrix(): void
    {
        if ($this->previousStatus !== SubscriptionStatus::Active
            || $this->nextStatus !== SubscriptionStatus::Active
            || $this->previousProgramId === null
            || $this->nextProgramId !== $this->previousProgramId
            || $this->previousIsFixed !== true
            || $this->nextIsFixed !== false) {
            throw SubscriptionInvariantException::withMessage(
                'Release placement must unfix the same program without moving it.',
            );
        }
    }
}
