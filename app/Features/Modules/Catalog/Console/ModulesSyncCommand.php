<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Console;

use App\Features\Modules\Catalog\UseCases\SyncModulesUseCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('modules:sync {--prune : Delete absent modules that have no references} {--force : Allow pruning in production}')]
#[Description('Reconcile the code-backed module registry with the persisted catalog')]
final class ModulesSyncCommand extends Command
{
    public function __construct(private readonly SyncModulesUseCase $useCase)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $prune = (bool) $this->option('prune');
        if ($prune && app()->environment('production') && ! $this->option('force')) {
            $this->error('The --force option is required with --prune in production.');

            return self::FAILURE;
        }

        try {
            $result = $this->useCase->execute($prune);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], [
            ['created', $result->created],
            ['updated', $result->updated],
            ['deactivated', $result->deactivated],
            ['capabilities_activated', $result->capabilitiesActivated],
            ['capabilities_deactivated', $result->capabilitiesDeactivated],
            ['pruned', $result->pruned],
            ['protected', $result->protected],
        ]);

        if ($result->protected > 0) {
            $this->warn('Synchronization completed with protected modules that could not be pruned.');

            return 2;
        }

        $this->info('Module catalog synchronized.');

        return self::SUCCESS;
    }
}
