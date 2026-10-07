<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rewards\Actions\BuildRewardFinancialRequestAction;
use App\Features\Rewards\Console\ProcessNegativePnlRewardsCommand;
use App\Features\Rewards\Console\ProcessVolumeRewardsCommand;
use App\Features\Rewards\Console\ReconcileRewardSettlementsCommand;
use App\Features\Rewards\Console\SettlePendingRewardsCommand;
use App\Features\Rewards\Console\VerifyCpaContextsCommand;
use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\Contracts\Ports\Input\RecordNegativePnlClosurePort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlReferralsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use App\Features\Rewards\Contracts\Ports\Output\RewardFinancialGatewayInterface;
use App\Features\Rewards\Contracts\Ports\Output\RewardSettlementGatewayInterface;
use App\Features\Rewards\Factories\NegativePnlPeriodsProviderFactory;
use App\Features\Rewards\Listeners\PublishCpaContextExpiredListener;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlCpaVerificationProgressRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlNegativePnlProcessingRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlRewardReadRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlRewardRepository;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlVolumeRewardProcessingRepository;
use App\Features\Rewards\Services\Adapters\FinanceRewardSettlementGateway;
use App\Features\Rewards\Services\Adapters\IamResolveNegativePnlReferralsAdapter;
use App\Features\Rewards\Services\Adapters\IamResolveRewardUplineAdapter;
use App\Features\Rewards\Services\Pushers\Service\ServiceEventPusher;
use App\Features\Rewards\UseCases\CaptureCpaContextUseCase;
use App\Features\Rewards\UseCases\ProcessVolumeRewardsUseCase;
use App\Features\Rewards\UseCases\ReconcileRewardSettlementsUseCase;
use App\Features\Rewards\UseCases\RecordNegativePnlClosureUseCase;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use App\Features\Rewards\UseCases\SettlePendingRewardsUseCase;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use App\Support\Messaging\Contracts\MessagePublisherInterface;
use App\Support\Messaging\Kafka\KafkaMessagePublisher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class RewardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('rewards.read.repositories.postgresql', fn () => new PostgreSqlRewardReadRepository(DB::connection()));
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
            fn (): PostgreSqlRewardRepository => new PostgreSqlRewardRepository(DB::connection(), $this->app->make(BuildRewardFinancialRequestAction::class)),
        );
        $this->app->singleton('rewards.volume-processing.repositories.postgresql', fn (): PostgreSqlVolumeRewardProcessingRepository => new PostgreSqlVolumeRewardProcessingRepository(DB::connection()));
        $this->app->singleton(MessagePublisherInterface::class, KafkaMessagePublisher::class);
        $this->app->singleton(ServiceEventPusher::class);
    }

    public function boot(): void
    {
        Event::listen(CpaContextExpired::class, PublishCpaContextExpiredListener::class);
        $this->commands([ProcessNegativePnlRewardsCommand::class]);
        $this->commands([VerifyCpaContextsCommand::class, SettlePendingRewardsCommand::class, ReconcileRewardSettlementsCommand::class, ProcessVolumeRewardsCommand::class]);
    }
}
