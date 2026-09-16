<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Actions;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionChangeData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionDetailData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionPlacementData;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;

final class PresentSubscriptionAction
{
    public function toData(Subscription $subscription): SubscriptionData
    {
        return new SubscriptionData(
            id: $subscription->id,
            external_user_id: $subscription->externalUserId,
            plan_id: $subscription->planId,
            origin: $subscription->origin->value,
            requires_approval: $subscription->requiresApproval,
            status: $subscription->status->value,
            activated_at: $subscription->activatedAt,
            closed_at: $subscription->closedAt,
            replaces_subscription_id: $subscription->replacesSubscriptionId,
            lock_version: $subscription->lockVersion,
            current_placement: $this->placementData($subscription->currentPlacement()),
            created_at: $subscription->createdAt,
            updated_at: $subscription->updatedAt,
        );
    }

    public function toDetail(Subscription $subscription): SubscriptionDetailData
    {
        return new SubscriptionDetailData(
            id: $subscription->id,
            external_user_id: $subscription->externalUserId,
            plan_id: $subscription->planId,
            origin: $subscription->origin->value,
            requires_approval: $subscription->requiresApproval,
            status: $subscription->status->value,
            activated_at: $subscription->activatedAt,
            closed_at: $subscription->closedAt,
            replaces_subscription_id: $subscription->replacesSubscriptionId,
            lock_version: $subscription->lockVersion,
            current_placement: $this->placementData($subscription->currentPlacement()),
            changes: array_map(
                fn (SubscriptionChange $change): SubscriptionChangeData => $this->changeData($change),
                $subscription->changes,
            ),
            created_at: $subscription->createdAt,
            updated_at: $subscription->updatedAt,
        );
    }

    private function placementData(?SubscriptionPlacement $placement): ?SubscriptionPlacementData
    {
        if ($placement === null) {
            return null;
        }

        return new SubscriptionPlacementData(
            id: $placement->id,
            program_id: $placement->programId,
            is_fixed: $placement->isFixed(),
            effective_from: $placement->effectiveFrom,
            effective_until: $placement->effectiveUntil,
        );
    }

    private function changeData(SubscriptionChange $change): SubscriptionChangeData
    {
        return new SubscriptionChangeData(
            id: $change->id,
            operation_id: $change->operationId,
            action: $change->action->value,
            actor_kind: $change->actorKind->value,
            actor_external_user_id: $change->actorExternalUserId,
            reason: $change->reason,
            previous_status: $change->previousStatus?->value,
            next_status: $change->nextStatus?->value,
            previous_program_id: $change->previousProgramId,
            next_program_id: $change->nextProgramId,
            previous_is_fixed: $change->previousIsFixed,
            next_is_fixed: $change->nextIsFixed,
            occurred_at: $change->occurredAt,
        );
    }
}
