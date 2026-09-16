<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Repositories\InMemory;

use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateOpenSubscriptionException;
use App\Features\Subscriptions\Catalog\Exceptions\DuplicateSubscriptionReplacementException;
use App\Features\Subscriptions\Catalog\Exceptions\ProgramNotOnSubscriptionPlanException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionConcurrencyException;
use App\Features\Subscriptions\Catalog\Exceptions\SubscriptionInvariantException;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use Closure;
use Throwable;

final class InMemorySubscriptionRepository implements SubscriptionRepositoryInterface
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var array<string, string> */
    private array $programPlanIds = [];

    public function registerProgram(string $programId, string $planId): void
    {
        $this->programPlanIds[$programId] = $planId;
    }

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->subscriptions), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->subscriptions = $snapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?Subscription
    {
        $subscription = $this->subscriptions[$id] ?? null;

        return $subscription === null ? null : $this->copy($subscription);
    }

    public function findOpenByExternalUserId(string $externalUserId): ?Subscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->externalUserId === $externalUserId && $subscription->status->isOpen()) {
                return $this->copy($subscription);
            }
        }

        return null;
    }

    public function resolvePlacementAt(string $subscriptionId, string $occurredAt): ?SubscriptionPlacement
    {
        $subscription = $this->findById($subscriptionId);
        if ($subscription === null) {
            return null;
        }

        $placement = $subscription->placementAt($occurredAt);

        return $placement === null ? null : unserialize(serialize($placement), ['allowed_classes' => true]);
    }

    public function create(Subscription $subscription): void
    {
        $this->assertWritable($subscription);

        if (isset($this->subscriptions[$subscription->id])) {
            throw SubscriptionInvariantException::withMessage(
                "Subscription [{$subscription->id}] already exists.",
            );
        }

        if ($subscription->status->isOpen() && $this->findOpenByExternalUserId($subscription->externalUserId) !== null) {
            throw DuplicateOpenSubscriptionException::forUser($subscription->externalUserId);
        }

        if ($subscription->replacesSubscriptionId !== null) {
            $this->assertReplacementAvailable($subscription->replacesSubscriptionId);
        }

        $this->subscriptions[$subscription->id] = $this->copy($subscription);
    }

    public function save(Subscription $subscription, int $expectedLockVersion): void
    {
        $stored = $this->subscriptions[$subscription->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw SubscriptionConcurrencyException::forSubscription($subscription->id);
        }

        $this->assertWritable($subscription);
        $this->assertOpenUniquenessOnSave($subscription, $stored);

        $subscription->lockVersion = $expectedLockVersion + 1;
        $this->subscriptions[$subscription->id] = $this->copy($subscription);
    }

    public function replace(
        Subscription $outgoing,
        int $expectedOutgoingLockVersion,
        Subscription $incoming,
    ): void {
        $this->transaction(function () use ($outgoing, $expectedOutgoingLockVersion, $incoming): void {
            $this->save($outgoing, $expectedOutgoingLockVersion);
            $this->create($incoming);
        });
    }

    private function assertWritable(Subscription $subscription): void
    {
        foreach ($subscription->placements as $placement) {
            $planId = $this->programPlanIds[$placement->programId] ?? null;
            if ($planId !== $subscription->planId) {
                throw ProgramNotOnSubscriptionPlanException::forProgram(
                    $placement->programId,
                    $subscription->planId,
                );
            }
        }

        foreach ($subscription->changes as $change) {
            $this->assertChangeProgramsBelong($change, $subscription->planId);
        }

        $openCount = count(array_filter(
            $subscription->placements,
            static fn (SubscriptionPlacement $placement): bool => $placement->isOpen(),
        ));

        if ($subscription->status === SubscriptionStatus::Active && $openCount !== 1) {
            throw SubscriptionInvariantException::withMessage(
                'Active subscriptions require exactly one open placement.',
            );
        }

        if (
            ($subscription->status === SubscriptionStatus::Pending
                || $subscription->status === SubscriptionStatus::Rejected)
            && $openCount !== 0
        ) {
            throw SubscriptionInvariantException::withMessage(
                'Pending and rejected subscriptions cannot have placements.',
            );
        }

        if ($subscription->status === SubscriptionStatus::Ended && $openCount !== 0) {
            throw SubscriptionInvariantException::withMessage(
                'Ended subscriptions cannot keep an open placement.',
            );
        }
    }

    private function assertChangeProgramsBelong(SubscriptionChange $change, string $planId): void
    {
        foreach ([$change->previousProgramId, $change->nextProgramId] as $programId) {
            if ($programId === null) {
                continue;
            }

            $programPlanId = $this->programPlanIds[$programId] ?? null;
            if ($programPlanId !== $planId) {
                throw ProgramNotOnSubscriptionPlanException::forProgram($programId, $planId);
            }
        }
    }

    private function assertOpenUniquenessOnSave(Subscription $incoming, Subscription $stored): void
    {
        if (! $incoming->status->isOpen()) {
            return;
        }

        foreach ($this->subscriptions as $existing) {
            if ($existing->id === $incoming->id) {
                continue;
            }

            if ($existing->externalUserId === $incoming->externalUserId && $existing->status->isOpen()) {
                throw DuplicateOpenSubscriptionException::forUser($incoming->externalUserId);
            }
        }
    }

    private function assertReplacementAvailable(string $replacesSubscriptionId): void
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->replacesSubscriptionId === $replacesSubscriptionId) {
                throw DuplicateSubscriptionReplacementException::forSubscription($replacesSubscriptionId);
            }
        }
    }

    private function copy(Subscription $subscription): Subscription
    {
        return unserialize(serialize($subscription), ['allowed_classes' => true]);
    }
}
