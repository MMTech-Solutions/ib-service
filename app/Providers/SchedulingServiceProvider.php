<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Scheduling\Console\DispatchSchedulingTasksCommand;
use App\Features\Scheduling\Console\ExecuteSchedulingRunCommand;
use App\Features\Scheduling\Console\ReconcileSchedulingRunsCommand;
use App\Features\Scheduling\Console\SuperviseSchedulingRunCommand;
use App\Features\Scheduling\Console\SyncSchedulingTasksCommand;
use App\Features\Scheduling\Repositories\InMemory\InMemorySchedulingRepository;
use App\Features\Scheduling\Repositories\PostgreSql\PostgreSqlSchedulingRepository;
use App\Features\Scheduling\Services\SchedulingExecutionMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('scheduling.repositories.postgresql', fn (): PostgreSqlSchedulingRepository => new PostgreSqlSchedulingRepository(DB::connection()));
        $this->app->singleton('scheduling.repositories.memory', fn (): InMemorySchedulingRepository => new InMemorySchedulingRepository);
        $this->app->singleton(SchedulingExecutionMutex::class, function (): SchedulingExecutionMutex {
            config()->set('database.connections.scheduling_mutex', config('database.connections.'.config('database.default')));

            return new SchedulingExecutionMutex(DB::connection('scheduling_mutex'));
        });
    }

    public function boot(): void
    {
        $this->commands([SyncSchedulingTasksCommand::class, DispatchSchedulingTasksCommand::class, ExecuteSchedulingRunCommand::class, ReconcileSchedulingRunsCommand::class, SuperviseSchedulingRunCommand::class]);
        Schedule::command('scheduling:dispatch')->everyMinute()->timezone('UTC')->name('scheduling.dispatch');
        Schedule::command('scheduling:reconcile')->everyMinute()->timezone('UTC')->name('scheduling.reconcile');
    }
}
