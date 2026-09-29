<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Subscriptions\Catalog\Repositories\InMemory\InMemorySubscriptionRepository;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\PostgreSqlSubscriptionRepository;
use App\Features\Subscriptions\Catalog\UseCases\ApplyProgressionPlacementUseCase;
use App\Features\Subscriptions\Catalog\UseCases\HasOpenSubscriptionsForPlanUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ListProgressionWindowSubscriptionsUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ResolveSubscriptionContextUseCase;
use App\Features\Subscriptions\Contracts\Ports\Input\ApplyProgressionPlacementPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListProgressionWindowSubscriptionsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HasOpenSubscriptionsForPlanPort::class, HasOpenSubscriptionsForPlanUseCase::class);
        $this->app->singleton(ResolveSubscriptionContextPort::class, ResolveSubscriptionContextUseCase::class);
        $this->app->singleton(ListProgressionWindowSubscriptionsPort::class, ListProgressionWindowSubscriptionsUseCase::class);
        $this->app->singleton(ApplyProgressionPlacementPort::class, ApplyProgressionPlacementUseCase::class);

        $this->app->singleton(
            'subscriptions.repositories.memory',
            fn (): InMemorySubscriptionRepository => new InMemorySubscriptionRepository,
        );
        $this->app->singleton(
            'subscriptions.repositories.postgresql',
            fn (Application $app): PostgreSqlSubscriptionRepository => new PostgreSqlSubscriptionRepository(
                DB::connection(),
            ),
        );
    }
}
