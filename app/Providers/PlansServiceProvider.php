<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Modules\Contracts\Events\V1\ModuleDeactivated;
use App\Features\Plans\Catalog\Jobs\DeactivatePlansWithoutOperationalModulesJob;
use App\Features\Plans\Catalog\Listeners\DeactivatePlansAfterModuleDeactivated;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryPlanRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlPlanRepository;
use App\Features\Plans\Catalog\UseCases\IsModuleReferencedUseCase;
use App\Features\Plans\Catalog\UseCases\LockPlanRowsUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePlanContextUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePlanSubscriptionContextUseCase;
use App\Features\Plans\Contracts\Ports\Input\IsModuleReferencedPort;
use App\Features\Plans\Contracts\Ports\Input\LockPlanRowsPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class PlansServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IsModuleReferencedPort::class, IsModuleReferencedUseCase::class);
        $this->app->singleton(ResolvePlanContextPort::class, ResolvePlanContextUseCase::class);
        $this->app->singleton(ResolvePlanSubscriptionContextPort::class, ResolvePlanSubscriptionContextUseCase::class);
        $this->app->singleton(LockPlanRowsPort::class, LockPlanRowsUseCase::class);
        $this->app->singleton(
            'plans.repositories.memory',
            fn (): InMemoryPlanRepository => new InMemoryPlanRepository,
        );
        $this->app->singleton(
            'plans.repositories.postgresql',
            fn (Application $app): PostgreSqlPlanRepository => new PostgreSqlPlanRepository(
                DB::connection(),
            ),
        );
    }

    public function boot(): void
    {
        Event::listen(ModuleDeactivated::class, DeactivatePlansAfterModuleDeactivated::class);
        Schedule::job(new DeactivatePlansWithoutOperationalModulesJob)
            ->everyFiveMinutes()
            ->name('plans.reconcile-without-operational-modules');
    }
}
