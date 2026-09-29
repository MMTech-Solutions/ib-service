<?php

declare(strict_types=1);

namespace App\Features\Progression\Console;

use App\Features\Progression\Services\ProgressionExecutionMutex;
use App\Features\Progression\UseCases\RecoverProgressionRunsUseCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('progression:recover-runs {--run= : Restrict recovery to one progression run UUID}')]
#[Description('Retry failed Progression run results and apply pending placements')]
final class RecoverProgressionRunsCommand extends Command
{
    public function __construct(
        private readonly RecoverProgressionRunsUseCase $useCase,
        private readonly ProgressionExecutionMutex $mutex,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $acquired = false;

        try {
            $acquired = $this->mutex->acquire();
            if (! $acquired) {
                Log::info('progression.recover_runs.skipped_locked');
                $this->info('Progression recovery skipped because another execution owns the lock.');

                return self::SUCCESS;
            }

            $result = $this->useCase->execute($this->option('run'));
        } catch (Throwable $throwable) {
            Log::error('progression.recover_runs.failed', ['exception_class' => $throwable::class]);
            $this->error('Progression recovery failed. Inspect the structured logs.');

            return self::FAILURE;
        } finally {
            if ($acquired) {
                $this->mutex->release();
            }
        }

        $this->table(['Metric', 'Count'], collect($result->toArray())->map(fn (mixed $count, string $metric): array => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
