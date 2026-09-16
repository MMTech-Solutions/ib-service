<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Subscriptions\Catalog\Repositories\InMemory\InMemorySubscriptionRepository;
use App\Features\Subscriptions\Catalog\Repositories\PostgreSql\PostgreSqlSubscriptionRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
