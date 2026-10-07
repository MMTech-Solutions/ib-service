<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\Exceptions\SchedulingException;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Jobs\ExecuteSchedulingRunJob;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Str;

final class AdmitSchedulingRunService
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingTaskRegistry $registry, private readonly QueueFactory $queues) {}

    public function manual(ManualRunData $input): RunData
    {
        return $this->admit($input->code, $input);
    }

    public function automatic(string $code, CarbonImmutable $minute): ?RunData
    {
        return $this->admit($code, scheduledAt: $minute->startOfMinute());
    }

    private function admit(string $code, ?ManualRunData $manual = null, ?CarbonImmutable $scheduledAt = null): ?RunData
    {
        $definition = $this->registry->find($code);
        $repository = $this->repositories->make();
        $key = $manual ? hash('sha256', $manual->idempotency_key) : null;
        $hash = $manual ? hash('sha256', json_encode([$code, $manual->actor_id, $manual->reason], JSON_THROW_ON_ERROR)) : null;

        return $repository->transaction(function () use ($repository, $code, $manual, $scheduledAt, $definition, $key, $hash): ?RunData {
            if ($key !== null) {
                $repository->reserveIdempotency($key);
                $existing = $repository->keyedRun($key);
                if ($existing !== null) {
                    if ($existing->request_hash !== $hash) {
                        throw SchedulingException::conflict('Idempotency key was already used for another request.');
                    }

                    return $existing;
                }
            }
            $task = $repository->task($code, true) ?? throw SchedulingException::missing();
            $scheduled = $scheduledAt?->toISOString();
            if ($scheduledAt !== null) {
                if (! $task->automatic_enabled || ! (new CronExpression($task->cron_expression))->isDue($scheduledAt, 'UTC')) {
                    return null;
                }
                $existing = $repository->automaticRun($code, $scheduled);
                if ($existing !== null) {
                    return $existing;
                }
            }
            $busy = $repository->activeRun($code) !== null;
            if ($manual !== null && $busy) {
                throw SchedulingException::conflict('Task already has a queued or running execution.');
            }
            $now = CarbonImmutable::now('UTC')->toISOString();
            $run = new RunData((string) Str::uuid7(), $code, $manual ? 'manual' : 'automatic', clone $task, $now,
                scheduled_at: $scheduled, actor_id: $manual?->actor_id, reason: $manual?->reason, idempotency_key: $key, request_hash: $hash, timeout_seconds: $definition->timeout_seconds);
            if ($busy || ! $this->registry->available($code)) {
                $run->status = 'skipped';
                $run->outcome = $busy ? 'task_busy' : 'feature_disabled';
                $run->finished_at = $now;
            }
            $repository->insertRun($run);
            if ($run->status === 'queued') {
                $this->queues->connection('scheduling')->push(new ExecuteSchedulingRunJob($run->id), queue: 'scheduling');
            }

            return $run;
        });
    }
}
