<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Programs\Catalog\Repositories\InMemory\InMemoryProgramRepository;
use App\Features\Programs\Catalog\Repositories\PostgreSql\PostgreSqlProgramRepository;
use App\Features\Programs\Catalog\UseCases\ResolveProgramContextUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramProgressionConfigurationUseCase;
use App\Features\Programs\Catalog\UseCases\ResolveProgramSubscriptionContextUseCase;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramProgressionConfigurationPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Programs\PaymentTemplates\Repositories\InMemory\InMemoryPaymentTemplateRepository;
use App\Features\Programs\PaymentTemplates\Repositories\PostgreSql\PostgreSqlPaymentTemplateRepository;
use App\Features\Programs\ProgressionTemplates\Repositories\InMemory\InMemoryProgressionTemplateRepository;
use App\Features\Programs\ProgressionTemplates\Repositories\PostgreSql\PostgreSqlProgressionTemplateRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class ProgramsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResolveProgramContextPort::class, ResolveProgramContextUseCase::class);
        $this->app->singleton(ResolveProgramProgressionConfigurationPort::class, ResolveProgramProgressionConfigurationUseCase::class);
        $this->app->singleton(ResolveProgramSubscriptionContextPort::class, ResolveProgramSubscriptionContextUseCase::class);
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
        $this->app->singleton('programs.payment-templates.repositories.memory', fn (): InMemoryPaymentTemplateRepository => new InMemoryPaymentTemplateRepository);
        $this->app->singleton('programs.payment-templates.repositories.postgresql', fn (): PostgreSqlPaymentTemplateRepository => new PostgreSqlPaymentTemplateRepository(DB::connection()));
        $this->app->singleton('programs.progression-templates.repositories.memory', fn (): InMemoryProgressionTemplateRepository => new InMemoryProgressionTemplateRepository);
        $this->app->singleton('programs.progression-templates.repositories.postgresql', fn (): PostgreSqlProgressionTemplateRepository => new PostgreSqlProgressionTemplateRepository(DB::connection()));
    }
}
