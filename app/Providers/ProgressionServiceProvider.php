<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Services\Adapters\ModulesFetchProgressionActivitiesAdapter;
use Illuminate\Support\ServiceProvider;

final class ProgressionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            FetchProgressionActivitiesPort::class,
            ModulesFetchProgressionActivitiesAdapter::class,
        );
    }
}
