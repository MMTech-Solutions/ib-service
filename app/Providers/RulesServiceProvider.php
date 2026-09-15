<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rules\Assignments\Repositories\InMemory\InMemoryRuleAssignmentRepository;
use App\Features\Rules\Assignments\Repositories\PostgreSql\PostgreSqlRuleAssignmentRepository;
use App\Features\Rules\Catalog\Repositories\InMemory\InMemoryRuleRepository;
use App\Features\Rules\Catalog\Repositories\PostgreSql\PostgreSqlRuleRepository;
use App\Features\Rules\Contracts\Strategies\RuleStrategyRegistryInterface;
use App\Features\Rules\Services\Strategies\ClosedRuleStrategyRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class RulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuleStrategyRegistryInterface::class, ClosedRuleStrategyRegistry::class);
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
