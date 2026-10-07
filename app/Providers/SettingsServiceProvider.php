<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Settings\Console\SyncSettingsCommand;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\Settings\Repositories\PostgreSql\PostgreSqlSettingRepository;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use App\Features\Settings\UseCases\ResolveSettingsUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SettingRepositoryInterface::class, fn (): PostgreSqlSettingRepository => new PostgreSqlSettingRepository(DB::connection(), $this->app->make(Encrypter::class)));
        $this->app->bind(ResolveSettingsPort::class, ResolveSettingsUseCase::class);
    }

    public function boot(): void
    {
        $this->commands([SyncSettingsCommand::class]);
        RateLimiter::for('module-connection-certification', fn (): Limit => Limit::perMinute(10)->by('connection-certification:'.$this->app->make(UserContext::class)->id()));
    }
}
