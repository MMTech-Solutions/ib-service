<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Progression\Console\CloseProgressionWindowsCommand;
use App\Features\Progression\Console\RecoverProgressionRunsCommand;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\Repositories\InMemory\InMemoryActivityDistributionRepository;
use App\Features\Progression\Repositories\InMemory\InMemoryActivityEvaluationRepository;
use App\Features\Progression\Repositories\InMemory\InMemoryProgressionRunRepository;
use App\Features\Progression\Repositories\PostgreSql\PostgreSqlActivityDistributionRepository;
use App\Features\Progression\Repositories\PostgreSql\PostgreSqlActivityEvaluationRepository;
use App\Features\Progression\Repositories\PostgreSql\PostgreSqlProgressionRunRepository;
use App\Features\Progression\Services\Adapters\IamResolveReferralUplineAdapter;
use App\Features\Progression\Services\Adapters\ModulesFetchProgressionActivitiesAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class ProgressionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            FetchProgressionActivitiesPort::class,
            ModulesFetchProgressionActivitiesAdapter::class,
        );
        $this->app->singleton(ResolveReferralUplinePort::class, IamResolveReferralUplineAdapter::class);
        $this->app->singleton('progression.distributions.repositories.memory', fn (): InMemoryActivityDistributionRepository => new InMemoryActivityDistributionRepository);
        $this->app->singleton('progression.distributions.repositories.postgresql', fn (): PostgreSqlActivityDistributionRepository => new PostgreSqlActivityDistributionRepository(DB::connection()));
        $this->app->singleton(
            'progression.evaluations.repositories.memory',
            fn (): InMemoryActivityEvaluationRepository => new InMemoryActivityEvaluationRepository,
        );
        $this->app->singleton(
            'progression.evaluations.repositories.postgresql',
            fn (Application $app): PostgreSqlActivityEvaluationRepository => new PostgreSqlActivityEvaluationRepository(
                DB::connection(),
            ),
        );
        $this->app->singleton('progression.runs.repositories.memory', fn (): InMemoryProgressionRunRepository => new InMemoryProgressionRunRepository);
        $this->app->singleton('progression.runs.repositories.postgresql', fn (): PostgreSqlProgressionRunRepository => new PostgreSqlProgressionRunRepository(DB::connection()));
    }

    public function boot(): void
    {
        $this->commands([CloseProgressionWindowsCommand::class, RecoverProgressionRunsCommand::class]);
        Schedule::command('progression:close-windows')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
    }
}
