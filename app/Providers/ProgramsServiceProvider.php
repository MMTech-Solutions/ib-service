<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use App\Features\Programs\Catalog\Repositories\PostgreSql\PostgreSqlNegativePnlConfigurationRepository;
use App\Features\Programs\Catalog\Repositories\PostgreSql\PostgreSqlProgramRepository;
use App\Features\Programs\Catalog\Repositories\PostgreSql\PostgreSqlProgramVolumeRewardConfigurationRepository;
use App\Features\Programs\Catalog\UseCases\ListNegativePnlConfigurationsUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveNegativePnlProgramConfigurationUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramContextUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramCpaSymbolsUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramProgressionConfigurationUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramSubscriptionContextUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgressionTargetProgramUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveVolumeRewardDistributionLimitUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveVolumeRewardProgramConfigurationUseCase;
use App\Features\Programs\Contracts\Ports\Input\ListNegativePnlConfigurationsPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveNegativePnlProgramConfigurationPort;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateRatesPort;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateVersionPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramCpaSymbolsPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramProgressionConfigurationPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTargetProgramPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTemplateVersionPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardDistributionLimitPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardProgramConfigurationPort;
use App\Features\Programs\PaymentTemplates\Repositories\InMemory\InMemoryPaymentTemplateRepository;
use App\Features\Programs\PaymentTemplates\Repositories\PostgreSql\PostgreSqlPaymentTemplateRepository;
use App\Features\Programs\PaymentTemplates\UseCases\ResolvePaymentTemplateRatesUseCase;
use App\Features\Programs\PaymentTemplates\UseCases\ResolvePaymentTemplateVersionUseCase;
use App\Features\Programs\ProgressionTemplates\Repositories\InMemory\InMemoryProgressionTemplateRepository;
use App\Features\Programs\ProgressionTemplates\Repositories\PostgreSql\PostgreSqlProgressionTemplateRepository;
use App\Features\Programs\ProgressionTemplates\UseCases\ResolveProgressionTemplateVersionUseCase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ProgramsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResolvePaymentTemplateVersionPort::class, ResolvePaymentTemplateVersionUseCase::class);
        $this->app->singleton(ResolveProgressionTemplateVersionPort::class, ResolveProgressionTemplateVersionUseCase::class);
        $this->app->singleton(ListNegativePnlConfigurationsPort::class, ListNegativePnlConfigurationsUseCase::class);
        $this->app->singleton(ResolveNegativePnlProgramConfigurationPort::class, ResolveNegativePnlProgramConfigurationUseCase::class);
        $this->app->singleton(ResolvePaymentTemplateRatesPort::class, ResolvePaymentTemplateRatesUseCase::class);
        $this->app->singleton('programs.negative-pnl-configurations.repositories.postgresql', fn (): PostgreSqlNegativePnlConfigurationRepository => new PostgreSqlNegativePnlConfigurationRepository(DB::connection()));
        $this->app->singleton(ResolveProgramContextPort::class, ResolveProgramContextUseCase::class);
        $this->app->singleton(ResolveProgramCpaSymbolsPort::class, ResolveProgramCpaSymbolsUseCase::class);
        $this->app->singleton(ResolveProgramProgressionConfigurationPort::class, ResolveProgramProgressionConfigurationUseCase::class);
        $this->app->singleton(ResolveProgramSubscriptionContextPort::class, ResolveProgramSubscriptionContextUseCase::class);
        $this->app->singleton(ResolveProgressionTargetProgramPort::class, ResolveProgressionTargetProgramUseCase::class);
        $this->app->singleton(ResolveVolumeRewardDistributionLimitPort::class, ResolveVolumeRewardDistributionLimitUseCase::class);
        $this->app->singleton(ResolveVolumeRewardProgramConfigurationPort::class, ResolveVolumeRewardProgramConfigurationUseCase::class);
        $this->app->singleton(
            'programs.repositories.memory',
            fn (): InMemoryProgramRepository => new InMemoryProgramRepository,
        );
        $this->app->singleton(
            'programs.repositories.postgresql',
            fn (Application $app): PostgreSqlProgramRepository => new PostgreSqlProgramRepository(
                DB::connection(),
            ),
        );
        $this->app->singleton('programs.volume-reward-configurations.repositories.postgresql', fn (): PostgreSqlProgramVolumeRewardConfigurationRepository => new PostgreSqlProgramVolumeRewardConfigurationRepository(DB::connection()));
        $this->app->singleton('programs.payment-templates.repositories.memory', fn (): InMemoryPaymentTemplateRepository => new InMemoryPaymentTemplateRepository);
        $this->app->singleton('programs.payment-templates.repositories.postgresql', fn (): PostgreSqlPaymentTemplateRepository => new PostgreSqlPaymentTemplateRepository(DB::connection()));
        $this->app->singleton('programs.progression-templates.repositories.memory', fn (): InMemoryProgressionTemplateRepository => new InMemoryProgressionTemplateRepository);
        $this->app->singleton('programs.progression-templates.repositories.postgresql', fn (): PostgreSqlProgressionTemplateRepository => new PostgreSqlProgressionTemplateRepository(DB::connection()));
    }
}
