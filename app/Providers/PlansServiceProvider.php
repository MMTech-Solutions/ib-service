<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Modules\Contracts\Events\V1\ModuleDeactivated;
use App\Features\Plans\Catalog\Console\ReconcilePlansWithoutOperationalModulesCommand;
use App\Features\Plans\Catalog\Listeners\DeactivatePlansAfterModuleDeactivated;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryPaymentTemplateBindingRepository;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryPlanRepository;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryProgressionTemplateBindingRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlPaymentTemplateBindingRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlPlanRepository;
use App\Features\Plans\Catalog\Repositories\PostgreSql\PostgreSqlProgressionTemplateBindingRepository;
use App\Features\Plans\Catalog\UseCases\IsModuleReferencedUseCase;
use App\Features\Plans\Catalog\UseCases\ListActivePlansForProgressionUseCase;
use App\Features\Plans\Catalog\UseCases\LockPlanRowsUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePaymentTemplateBindingUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePlanContextUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePlanProgressionContextUseCase;
use App\Features\Plans\Catalog\UseCases\ResolvePlanSubscriptionContextUseCase;
use App\Features\Plans\Contracts\Ports\Input\IsModuleReferencedPort;
use App\Features\Plans\Contracts\Ports\Input\ListActivePlansForProgressionPort;
use App\Features\Plans\Contracts\Ports\Input\LockPlanRowsPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePaymentTemplateBindingPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class PlansServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('plans.payment-template-bindings.repositories.memory', fn (): InMemoryPaymentTemplateBindingRepository => new InMemoryPaymentTemplateBindingRepository);
        $this->app->singleton('plans.progression-template-bindings.repositories.postgresql', fn (): PostgreSqlProgressionTemplateBindingRepository => new PostgreSqlProgressionTemplateBindingRepository(DB::connection()));
        $this->app->singleton('plans.progression-template-bindings.repositories.memory', fn (): InMemoryProgressionTemplateBindingRepository => new InMemoryProgressionTemplateBindingRepository);
        $this->app->singleton(ResolvePaymentTemplateBindingPort::class, ResolvePaymentTemplateBindingUseCase::class);
        $this->app->singleton('plans.payment-template-bindings.repositories.postgresql', fn (): PostgreSqlPaymentTemplateBindingRepository => new PostgreSqlPaymentTemplateBindingRepository(DB::connection()));
        $this->app->singleton(IsModuleReferencedPort::class, IsModuleReferencedUseCase::class);
        $this->app->singleton(ResolvePlanContextPort::class, ResolvePlanContextUseCase::class);
        $this->app->singleton(ResolvePlanProgressionContextPort::class, ResolvePlanProgressionContextUseCase::class);
        $this->app->singleton(ResolvePlanSubscriptionContextPort::class, ResolvePlanSubscriptionContextUseCase::class);
        $this->app->singleton(LockPlanRowsPort::class, LockPlanRowsUseCase::class);
        $this->app->singleton(ListActivePlansForProgressionPort::class, ListActivePlansForProgressionUseCase::class);
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
        $this->commands([ReconcilePlansWithoutOperationalModulesCommand::class]);
    }
}
