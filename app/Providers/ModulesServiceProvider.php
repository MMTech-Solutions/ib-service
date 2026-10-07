<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Modules\Catalog\Console\ModulesSyncCommand;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Repositories\InMemory\InMemoryModuleRepository;
use App\Features\Modules\Catalog\Repositories\PostgreSql\PostgreSqlModuleRepository;
use App\Features\Modules\Catalog\Services\Adapters\PlanModuleReferenceGuard;
use App\Features\Modules\Catalog\Services\ModuleActivityRejectionEvidence;
use App\Features\Modules\Catalog\UseCases\CertifyProviderConnectionUseCase;
use App\Features\Modules\Catalog\UseCases\ListCertifiedDepositsUseCase;
use App\Features\Modules\Catalog\UseCases\ListCpaEvidenceUseCase;
use App\Features\Modules\Catalog\UseCases\ListInstrumentCatalogUseCase;
use App\Features\Modules\Catalog\UseCases\ListModuleActivitySubscriptionsUseCase;
use App\Features\Modules\Catalog\UseCases\ListProgressionActivitiesUseCase;
use App\Features\Modules\Catalog\UseCases\ListVolumeRewardActivitiesUseCase;
use App\Features\Modules\Catalog\UseCases\NormalizeVolumeRewardEventUseCase;
use App\Features\Modules\Catalog\UseCases\ResolveCpaEvidenceCapabilityUseCase;
use App\Features\Modules\Catalog\UseCases\ResolveModulesUseCase;
use App\Features\Modules\Catalog\UseCases\ResolveVolumeRewardModulesUseCase;
use App\Features\Modules\Catalog\UseCases\ValidateModuleActivitySubscriptionsUseCase;
use App\Features\Modules\Contracts\Ports\Input\CertifyProviderConnectionPort;
use App\Features\Modules\Contracts\Ports\Input\ListCertifiedDepositsPort;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;
use App\Features\Modules\Contracts\Ports\Input\ListModuleActivitySubscriptionsPort;
use App\Features\Modules\Contracts\Ports\Input\ListProgressionActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveCpaEvidenceCapabilityPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveVolumeRewardModulesPort;
use App\Features\Modules\Contracts\Ports\Input\ValidateModuleActivitySubscriptionsPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CertifyProviderConnectionPort::class, CertifyProviderConnectionUseCase::class);
        $this->app->singleton(ListModuleActivitySubscriptionsPort::class, ListModuleActivitySubscriptionsUseCase::class);
        $this->app->singleton(ValidateModuleActivitySubscriptionsPort::class, ValidateModuleActivitySubscriptionsUseCase::class);
        $this->app->singleton(NormalizeVolumeRewardEventPort::class, NormalizeVolumeRewardEventUseCase::class);
        $this->app->singleton(ResolveVolumeRewardModulesPort::class, ResolveVolumeRewardModulesUseCase::class);
        $this->app->singleton(ResolveCpaEvidenceCapabilityPort::class, ResolveCpaEvidenceCapabilityUseCase::class);
        $this->app->singleton(ListCertifiedDepositsPort::class, ListCertifiedDepositsUseCase::class);
        $this->app->singleton(ResolveModulesPort::class, ResolveModulesUseCase::class);
        $this->app->singleton(ListProgressionActivitiesPort::class, ListProgressionActivitiesUseCase::class);
        $this->app->singleton(ListVolumeRewardActivitiesPort::class, ListVolumeRewardActivitiesUseCase::class);
        $this->app->singleton(ListInstrumentCatalogPort::class, ListInstrumentCatalogUseCase::class);
        $this->app->singleton(ListCpaEvidencePort::class, ListCpaEvidenceUseCase::class);
        $this->app->singleton(ModuleActivityRejectionEvidence::class);
        $this->app->singleton(ModuleReferenceGuardInterface::class, PlanModuleReferenceGuard::class);
        $this->app->singleton(
            'modules.repositories.memory',
            fn (Application $app): InMemoryModuleRepository => new InMemoryModuleRepository(
                $app->make(ModuleReferenceGuardInterface::class),
            ),
        );
        $this->app->singleton(
            'modules.repositories.postgresql',
            fn (Application $app): PostgreSqlModuleRepository => new PostgreSqlModuleRepository(
                DB::connection(),
                $app->make(ModuleReferenceGuardInterface::class),
            ),
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([ModulesSyncCommand::class]);
    }
}
