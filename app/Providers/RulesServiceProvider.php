<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rules\Assignments\Repositories\InMemory\InMemoryCpaConfigurationRepository;
use App\Features\Rules\Assignments\Repositories\InMemory\InMemoryRuleAssignmentRepository;
use App\Features\Rules\Assignments\Repositories\PostgreSql\PostgreSqlCpaConfigurationRepository;
use App\Features\Rules\Assignments\Repositories\PostgreSql\PostgreSqlRuleAssignmentRepository;
use App\Features\Rules\Assignments\UseCases\ResolveCpaRuleContextUseCase;
use App\Features\Rules\Assignments\UseCases\ResolveNegativePnlRuleContextUseCase;
use App\Features\Rules\Assignments\UseCases\ResolvePointsContributionContextUseCase;
use App\Features\Rules\Assignments\UseCases\ResolveVolumeRewardRuleContextUseCase;
use App\Features\Rules\Catalog\Repositories\InMemory\InMemoryRuleRepository;
use App\Features\Rules\Catalog\Repositories\PostgreSql\PostgreSqlRuleRepository;
use App\Features\Rules\Contracts\Ports\Input\ResolveCpaRuleContextPort;
use App\Features\Rules\Contracts\Ports\Input\ResolveNegativePnlRuleContextPort;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Rules\Contracts\Ports\Input\ResolveVolumeRewardRuleContextPort;
use App\Features\Rules\Contracts\Strategies\RuleStrategyRegistryInterface;
use App\Features\Rules\Services\Strategies\ClosedRuleStrategyRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class RulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('rules.cpa.repositories.postgresql', fn () => new PostgreSqlCpaConfigurationRepository(DB::connection()));
        $this->app->singleton('rules.cpa.repositories.memory', InMemoryCpaConfigurationRepository::class);
        $this->app->singleton(ResolveNegativePnlRuleContextPort::class, ResolveNegativePnlRuleContextUseCase::class);
        $this->app->singleton(RuleStrategyRegistryInterface::class, ClosedRuleStrategyRegistry::class);
        $this->app->singleton(
            ResolvePointsContributionContextPort::class,
            ResolvePointsContributionContextUseCase::class,
        );
        $this->app->singleton(ResolveCpaRuleContextPort::class, ResolveCpaRuleContextUseCase::class);
        $this->app->singleton(ResolveVolumeRewardRuleContextPort::class, ResolveVolumeRewardRuleContextUseCase::class);
        $this->app->singleton(
            'rules.repositories.memory',
            fn (): InMemoryRuleRepository => new InMemoryRuleRepository,
        );
        $this->app->singleton(
            'rules.repositories.postgresql',
            fn (Application $app): PostgreSqlRuleRepository => new PostgreSqlRuleRepository(
                DB::connection(),
            ),
        );
        $this->app->singleton(
            'rules.assignments.repositories.memory',
            fn (): InMemoryRuleAssignmentRepository => new InMemoryRuleAssignmentRepository,
        );
        $this->app->singleton(
            'rules.assignments.repositories.postgresql',
            fn (Application $app): PostgreSqlRuleAssignmentRepository => new PostgreSqlRuleAssignmentRepository(
                DB::connection(),
            ),
        );
    }
}
