<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Console;

use App\Features\Scheduling\UseCases\ReconcileSchedulingRunsUseCase;
use Illuminate\Console\Command;

final class ReconcileSchedulingRunsCommand extends Command
{
    protected $signature = 'scheduling:reconcile';

    protected $description = 'scheduling:reconcile for the registered Scheduling catalog';

    public function handle(ReconcileSchedulingRunsUseCase $useCase): int
    {
        $this->info('Interrupted runs: '.$useCase->execute());

        return 0;
    }
}
