<?php

declare(strict_types=1);

namespace App\Features\Settings\Console;

use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\UseCases\SyncSettingsUseCase;
use Illuminate\Console\Command;
use Throwable;

final class SyncSettingsCommand extends Command
{
    protected $signature = 'settings:sync {--dry-run : Validate and show changes without writing}';

    protected $description = 'Synchronize domain settings with the code-backed catalog';

    public function handle(SyncSettingsUseCase $useCase): int
    {
        try {
            $result = $useCase->execute((bool) $this->option('dry-run'), 'cli:settings:sync');
            $this->table(['Metric', 'Count'], [['created', $result->created], ['updated', $result->updated], ['deleted', $result->deleted], ['unchanged', $result->unchanged]]);
            $this->info($result->dry_run ? 'Dry run completed. No changes written.' : 'Settings synchronized.');

            return self::SUCCESS;
        } catch (SettingException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Settings synchronization failed. Check database availability and encryption configuration.');

            return self::FAILURE;
        }
    }
}
