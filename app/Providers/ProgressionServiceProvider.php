<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Repositories\InMemory\InMemoryActivityEvaluationRepository;
use App\Features\Progression\Repositories\PostgreSql\PostgreSqlActivityEvaluationRepository;
use App\Features\Progression\Services\Adapters\ModulesFetchProgressionActivitiesAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ProgressionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            FetchProgressionActivitiesPort::class,
            ModulesFetchProgressionActivitiesAdapter::class,
        );
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
    }
}
