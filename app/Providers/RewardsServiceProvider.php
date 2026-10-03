<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rewards\Console\ProcessNegativePnlRewardsCommand;
use App\Features\Rewards\Console\ProcessVolumeRewardsCommand;
use App\Features\Rewards\Console\ReconcileRewardSettlementsCommand;
use App\Features\Rewards\Console\SettlePendingRewardsCommand;
use App\Features\Rewards\Console\VerifyCpaContextsCommand;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\Contracts\Ports\Input\RecordNegativePnlClosurePort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlReferralsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\Factories\NegativePnlPeriodsProviderFactory;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlCpaVerificationProgressRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlNegativePnlProcessingRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlRewardRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlVolumeRewardProcessingRepository;
use App\Features\Rewards\Services\Adapters\FinanceRewardSettlementGateway;
use App\Features\Rewards\Services\Adapters\IamResolveNegativePnlReferralsAdapter;
use App\Features\Rewards\Services\Adapters\IamResolveRewardUplineAdapter;
use App\Features\Rewards\UseCases\CaptureCpaContextUseCase;
use App\Features\Rewards\UseCases\ProcessVolumeRewardsUseCase;
use App\Features\Rewards\UseCases\ReconcileRewardSettlementsUseCase;
use App\Features\Rewards\UseCases\RecordNegativePnlClosureUseCase;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use App\Features\Rewards\UseCases\SettlePendingRewardsUseCase;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class RewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordNegativePnlClosurePort::class, RecordNegativePnlClosureUseCase::class);
        $this->app->singleton(ResolveNegativePnlReferralsPort::class, IamResolveNegativePnlReferralsAdapter::class);
        $this->app->singleton('rewards.negative-pnl-processing.repositories.postgresql', fn (): PostgreSqlNegativePnlProcessingRepository => new PostgreSqlNegativePnlProcessingRepository(DB::connection()));
        $this->app->singleton(CaptureCpaContextPort::class, CaptureCpaContextUseCase::class);
        $this->app->singleton(RewardSettlementGatewayInterface::class, FinanceRewardSettlementGateway::class);
        $this->app->singleton(RewardFinancialGatewayInterface::class, FinanceRewardSettlementGateway::class);
        $this->app->singleton(ResolveRewardUplinePort::class, IamResolveRewardUplineAdapter::class);
        $this->app->singleton(ResolveNegativePnlPeriodsPort::class, fn (Application $app): ResolveNegativePnlPeriodsPort => $app->make(NegativePnlPeriodsProviderFactory::class)->make('broker'));
        $this->app->singleton(VerifyCpaContextsUseCase::class);
        $this->app->singleton(SettlePendingRewardsUseCase::class);
        $this->app->singleton(ReconcileRewardSettlementsUseCase::class);
        $this->app->singleton(RecordVolumeRewardEventUseCase::class);
        $this->app->singleton(ProcessVolumeRewardsUseCase::class);
        $this->app->singleton(
            'rewards.cpa_progress.repositories.postgresql',
            fn (): PostgreSqlCpaVerificationProgressRepository => new PostgreSqlCpaVerificationProgressRepository(DB::connection()),
        );
        $this->app->singleton(
            'rewards.repositories.postgresql',
            fn (): PostgreSqlRewardRepository => new PostgreSqlRewardRepository(DB::connection()),
        );
        $this->app->singleton('rewards.volume-processing.repositories.postgresql', fn (): PostgreSqlVolumeRewardProcessingRepository => new PostgreSqlVolumeRewardProcessingRepository(DB::connection()));
    }

    public function boot(): void
    {
        $this->commands([ProcessNegativePnlRewardsCommand::class]);
        Schedule::command('rewards:process-negative-pnl')->everyMinute()->onOneServer()->withoutOverlapping()->when(fn (): bool => (bool) config('rewards.negative_pnl.enabled', false));
        $this->commands([VerifyCpaContextsCommand::class, SettlePendingRewardsCommand::class, ReconcileRewardSettlementsCommand::class, ProcessVolumeRewardsCommand::class]);
        Schedule::command('rewards:verify-cpa')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
        Schedule::command('rewards:settle-pending')->everyMinute()->onOneServer()->withoutOverlapping();
        Schedule::command('rewards:reconcile-settlements')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
        Schedule::command('rewards:process-volume')->everyMinute()->onOneServer()->withoutOverlapping();
    }
}
