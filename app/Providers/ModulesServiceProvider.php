<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Modules\Catalog\Console\ModulesSyncCommand;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Repositories\InMemory\InMemoryModuleRepository;
use App\Features\Modules\Catalog\Repositories\NoModuleReferences;
use App\Features\Modules\Catalog\Repositories\PostgreSql\PostgreSqlModuleRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleReferenceGuardInterface::class, NoModuleReferences::class);
        $this->app->singleton(
            'modules.repositories.memory',
            fn (Application $app): InMemoryModuleRepository => new InMemoryModuleRepository(
                $app->make(ModuleReferenceGuardInterface::class),
            ),
        );
        $this->app->singleton(
            'modules.repositories.postgresql',
            fn (Application $app): PostgreSqlModuleRepository => new PostgreSqlModuleRepository(
                DB::connection(),
                $app->make(ModuleReferenceGuardInterface::class),
            ),
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([ModulesSyncCommand::class]);
    }
}
