<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\UseCases\CaptureCpaContextUseCase;
use Illuminate\Support\ServiceProvider;

final class RewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CaptureCpaContextPort::class, CaptureCpaContextUseCase::class);
    }
}
