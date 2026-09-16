<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Catalog;

use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionChangeAction;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionOrigin;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SubscriptionDomainInvariantTest extends TestCase
{
    public function test_user_application_requires_approval_snapshot_and_forbids_replacement(): void
    {
        $this->expectException(SubscriptionInvariantException::class);

        new Subscription(
            id: (string) Str::uuid7(),
            externalUserId: (string) Str::uuid7(),
            planId: (string) Str::uuid7(),
            origin: SubscriptionOrigin::UserApplication,
            requiresApproval: null,
            status: SubscriptionStatus::Pending,
            activatedAt: null,
            closedAt: null,
            replacesSubscriptionId: null,
            lockVersion: 1,
            placements: [],
            changes: [],
            createdAt: $this->now(),
            updatedAt: $this->now(),
        );
    }

    public function test_admin_plan_change_requires_replacement_without_approval_snapshot(): void
    {
        $this->expectException(SubscriptionInvariantException::class);

        new Subscription(
            id: (string) Str::uuid7(),
            externalUserId: (string) Str::uuid7(),
            planId: (string) Str::uuid7(),
            origin: SubscriptionOrigin::AdminPlanChange,
            requiresApproval: true,
            status: SubscriptionStatus::Active,
            activatedAt: $this->now(),
            closedAt: null,
            replacesSubscriptionId: (string) Str::uuid7(),
            lockVersion: 1,
            placements: [],
            changes: [],
            createdAt: $this->now(),
            updatedAt: $this->now(),
        );
    }

    #[DataProvider('invalidChangeMatrices')]
    public function test_change_action_matrix(
        SubscriptionChangeAction $action,
        ?SubscriptionStatus $previousStatus,
        ?SubscriptionStatus $nextStatus,
        ?string $previousProgramId,
        ?string $nextProgramId,
        ?bool $previousIsFixed,
        ?bool $nextIsFixed,
        ?string $reason,
    ): void {
        $this->expectException(SubscriptionInvariantException::class);

        SubscriptionChange::record(
            id: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            subscriptionId: (string) Str::uuid7(),
            action: $action,
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: (string) Str::uuid7(),
            reason: $reason,
            previousStatus: $previousStatus,
            nextStatus: $nextStatus,
            previousProgramId: $previousProgramId,
            nextProgramId: $nextProgramId,
            previousIsFixed: $previousIsFixed,
            nextIsFixed: $nextIsFixed,
            occurredAt: $this->now(),
        );
    }

    /** @return array<string, array<int, mixed>> */
    public static function invalidChangeMatrices(): array
    {
        $programId = (string) Str::uuid7();

        return [
            'reject without reason' => [
                SubscriptionChangeAction::Reject,
                SubscriptionStatus::Pending,
                SubscriptionStatus::Rejected,
                null,
                null,
                null,
                null,
                null,
            ],
            'approve without unfixed placement' => [
                SubscriptionChangeAction::Approve,
                SubscriptionStatus::Pending,
                SubscriptionStatus::Active,
                null,
                $programId,
                null,
                true,
                null,
            ],
            'placement snapshot split' => [
                SubscriptionChangeAction::Cancel,
                SubscriptionStatus::Active,
                SubscriptionStatus::Ended,
                $programId,
                $programId,
                true,
                null,
                null,
            ],
        ];
    }

    private function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
