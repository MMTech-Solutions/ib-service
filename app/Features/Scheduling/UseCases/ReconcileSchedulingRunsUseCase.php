<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\SchedulingRunLifecycle;
use Carbon\CarbonImmutable;

final class ReconcileSchedulingRunsUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingRunLifecycle $lifecycle) {}

    public function execute(): int
    {
        $count = 0;
        $now = CarbonImmutable::now('UTC');
        foreach ($this->repositories->make()->staleRuns($now->subSeconds((int) config('scheduling.stale_seconds', 120))->toISOString()) as $run) {
            if ($run->status === 'queued' && CarbonImmutable::parse($run->accepted_at)->addSeconds((int) config('queue.connections.scheduling.retry_after') + 120)->greaterThan($now)) {
                continue;
            }
            if ($this->lifecycle->interrupt($run->id, 'runner_interrupted')) {
                $count++;
            }
        }

        return $count;
    }
}
