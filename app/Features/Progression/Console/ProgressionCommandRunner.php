<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\DTOs\ProgressionExecutionReportData;
use App\Features\Progression\Services\ProgressionExecutionEvidence;
use App\Features\Progression\Services\ProgressionExecutionMutex;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ProgressionCommandRunner
{
    public function __construct(private readonly DomainClock $clock, private readonly ProgressionExecutionMutex $mutex, private readonly ProgressionExecutionEvidence $evidence) {}

    /** @param \Closure(): void $validate @param \Closure(): array{outcome: string, counts: array<string, int>} $execute */
    public function execute(Command $command, string $operation, \Closure $validate, \Closure $execute): int
    {
        $this->evidence->reset($operation);
        $counts = match ($operation) {
            'evaluate' => array_fill_keys(['evaluations', 'accepted', 'excluded', 'retryable_failures'], 0),
            'close' => array_fill_keys(['runs_created', 'runs_completed', 'runs_with_errors', 'results_completed', 'results_skipped', 'results_failed', 'placements_applied', 'placements_unchanged', 'placements_fixed', 'placements_not_active', 'placements_failed'], 0),
            'recover' => array_fill_keys(['results_recovered', 'results_failed', 'runs_completed', 'placements_applied', 'placements_unchanged', 'placements_fixed', 'placements_not_active', 'placements_failed'], 0),
        };
        $report = ['version' => 1, 'operation' => $operation, 'execution_id' => (string) Str::uuid(), 'status' => 'executed',
            'observed_domain_time_utc' => null, 'context_id' => null, 'sequence' => null, 'outcome' => null,
            'counts' => $counts, 'ids' => []];
        $acquired = false;
        $code = 0;
        $this->clock->end();
        try {
            try {
                $validate();
            } catch (\InvalidArgumentException) {
                $report['status'] = 'invalid';
                $code = 2;
            }
            if ($code === 0) {
                $this->clock->begin();
                $report['observed_domain_time_utc'] = $this->clock->now()->toISOString();
                $report = [...$report, ...$this->clock->context()];
                $acquired = $this->mutex->acquire();
                if (! $acquired) {
                    $report['status'] = 'locked';
                } else {
                    $result = $execute();
                    $report['outcome'] = $result['outcome'];
                    $report['counts'] = $result['counts'];
                    if (($result['counts']['retryable_failures'] ?? 0) > 0 || ($result['counts']['results_failed'] ?? 0) > 0 || ($result['counts']['placements_failed'] ?? 0) > 0) {
                        $report['status'] = 'failed';
                        $code = 1;
                    }
                }
            }
        } catch (\Throwable $error) {
            $report['status'] = 'failed';
            $code = 1;
            $report['counts'] = [...$counts, ...$this->evidence->counts];
            Log::error('progression.command.failed', ['execution_id' => $report['execution_id'], 'operation' => $operation, 'exception_class' => $error::class]);
        } finally {
            if ($acquired) {
                try {
                    $this->mutex->release();
                } catch (\Throwable $error) {
                    $report['status'] = 'failed';
                    $code = 1;
                    Log::error('progression.command.unlock_failed', ['execution_id' => $report['execution_id'], 'exception_class' => $error::class]);
                }
            }
            $this->clock->end();
        }
        $report['ids'] = $this->evidence->ids;
        $report = ProgressionExecutionReportData::from($report)->toArray();
        if ($command->option('json')) {
            $command->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $command->line('Progression '.$operation.': '.$report['status']);
            $command->table(['Metric', 'Count'], collect($report['counts'])->map(fn (int $value, string $name): array => [$name, $value])->values()->all());
        }
        if ($code !== 0) {
            $command->getOutput()->getErrorStyle()->writeln($code === 2 ? 'Invalid Progression input.' : 'Progression execution has technical failures. Inspect structured evidence.');
        }

        return $code;
    }
}
