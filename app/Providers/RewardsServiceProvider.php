<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rewards\Console\VerifyCpaContextsCommand;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\UseCases\CaptureCpaContextUseCase;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class RewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CaptureCpaContextPort::class, CaptureCpaContextUseCase::class);
        $this->app->singleton(VerifyCpaContextsUseCase::class);
    }

    public function boot(): void
    {
        $this->commands([VerifyCpaContextsCommand::class]);
        Schedule::command('rewards:verify-cpa')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
    }
}
