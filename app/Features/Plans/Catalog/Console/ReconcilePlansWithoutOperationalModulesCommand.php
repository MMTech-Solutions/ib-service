<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Console;

use App\Features\Plans\Catalog\UseCases\DeactivatePlansWithoutOperationalModulesUseCase;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Console\Command;

final class ReconcilePlansWithoutOperationalModulesCommand extends Command
{
    protected $signature = 'plans:reconcile-without-operational-modules';

    protected $description = 'Deactivate plans without operational modules';

    public function handle(DeactivatePlansWithoutOperationalModulesUseCase $useCase, DomainClock $clock): int
    {
        $clock->end();
        try {
            $clock->begin();
            $this->info('Deactivated plans: '.$useCase->execute());

            return 0;
        } finally {
            $clock->end();
        }
    }
}
