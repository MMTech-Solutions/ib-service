<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use App\Features\Programs\Contracts\Data\V1\CaptureProgressionLadderQueryData;
use App\Features\Programs\Contracts\Ports\Input\CaptureProgressionLadderPort;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Subscriptions\Contracts\Data\V1\ListProgressionWindowSubscriptionsQueryData;
use Carbon\CarbonImmutable;

final class PrepareProgressionRunService
{
    public function __construct(private readonly CaptureProgressionLadderPort $ladder, private readonly ProgressionInterFeatureGateways $gateways) {}

    public function execute(ProgressionRunRepositoryInterface $repository, ProgressionRun $run, CarbonImmutable $now): ProgressionRunSnapshotData
    {
        $legacy = $run->legacy;

        return $repository->snapshot($run->id) ?? $repository->consistentRead(function () use ($repository, $run, $now, $legacy): ProgressionRunSnapshotData {
            $existing = $repository->snapshot($run->id);
            if ($existing !== null) {
                return $existing;
            }
            $ladder = $this->ladder->execute(new CaptureProgressionLadderQueryData($run->planId));
            $participants = [];
            foreach ($this->gateways->windowSubscriptions()->list(new ListProgressionWindowSubscriptionsQueryData($run->planId, $run->window->startsAtIso(), $run->window->endsAtIso())) as $subscription) {
                $participants[] = [
                    'subscription_id' => $subscription->subscription_id,
                    'is_evaluable' => $subscription->is_evaluable,
                    'contribution_ids' => $repository->contributionIds($run->planId, $subscription->subscription_id, $run->window),
                    'total_points' => $repository->sumAcceptedContributionPoints($run->planId, $subscription->subscription_id, $run->window)->value(),
                ];
                $repository->findOrCreateResult($run->id, $subscription->subscription_id, $now);
            }
            $snapshot = new ProgressionRunSnapshotData($ladder, $participants, $now->toISOString(), $legacy);
            if ($legacy) {
                $known = array_column($participants, 'subscription_id');
                foreach ($repository->failedResults($run->id) as $retry) {
                    if (! in_array($retry->result->subscriptionId, $known, true)) {
                        $participants[] = ['subscription_id' => $retry->result->subscriptionId, 'is_evaluable' => true,
                            'contribution_ids' => $repository->contributionIds($run->planId, $retry->result->subscriptionId, $run->window),
                            'total_points' => $repository->sumAcceptedContributionPoints($run->planId, $retry->result->subscriptionId, $run->window)->value()];
                    }
                }
                $snapshot = new ProgressionRunSnapshotData($ladder, $participants, $now->toISOString(), true);
            }
            $repository->saveSnapshot($run->id, $snapshot);

            return $snapshot;
        });
    }

    public function target(ProgressionRunSnapshotData $snapshot, string $points): string
    {
        [$integer] = explode('.', $points, 2);
        $integer = ltrim($integer, '0') ?: '0';
        $target = null;
        foreach ($snapshot->ladder->programs as $program) {
            $threshold = $program['entry_threshold'];
            if (strlen($integer) > strlen($threshold) || (strlen($integer) === strlen($threshold) && strcmp($integer, $threshold) >= 0)) {
                $target = $program['program_id'];
            }
        }

        return $target ?? throw new \LogicException('Frozen progression ladder has no eligible target.');
    }
}
