<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Subscriptions\Catalog\Repositories\InMemory\InMemorySubscriptionRepository;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\PostgreSqlNegativePnlSubscriptionRepository;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\PostgreSqlSubscriptionRepository;
use App\Features\Subscriptions\Catalog\UseCases\ApplyProgressionPlacementUseCase;
use App\Features\Subscriptions\Catalog\UseCases\HasOpenSubscriptionsForPlanUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ListNegativePnlSubscriptionSegmentsUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ListNegativePnlSubscriptionsUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ListProgressionWindowSubscriptionsUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ResolveNegativePnlSubscriptionContextUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ResolveRewardBackfillStartUseCase;
use App\Features\Subscriptions\Catalog\UseCases\ResolveSubscriptionContextUseCase;
use App\Features\Subscriptions\Contracts\Ports\Input\ApplyProgressionPlacementPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionSegmentsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListProgressionWindowSubscriptionsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveNegativePnlSubscriptionContextPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveRewardBackfillStartPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListNegativePnlSubscriptionSegmentsPort::class, ListNegativePnlSubscriptionSegmentsUseCase::class);
        $this->app->singleton(ListNegativePnlSubscriptionsPort::class, ListNegativePnlSubscriptionsUseCase::class);
        $this->app->singleton(ResolveNegativePnlSubscriptionContextPort::class, ResolveNegativePnlSubscriptionContextUseCase::class);
        $this->app->singleton('subscriptions.negative-pnl.repositories.postgresql', fn (): PostgreSqlNegativePnlSubscriptionRepository => new PostgreSqlNegativePnlSubscriptionRepository(DB::connection()));
        $this->app->singleton(HasOpenSubscriptionsForPlanPort::class, HasOpenSubscriptionsForPlanUseCase::class);
        $this->app->singleton(ResolveSubscriptionContextPort::class, ResolveSubscriptionContextUseCase::class);
        $this->app->singleton(ResolveRewardBackfillStartPort::class, ResolveRewardBackfillStartUseCase::class);
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
