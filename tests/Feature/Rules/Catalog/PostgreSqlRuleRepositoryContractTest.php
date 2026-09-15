<?php

declare(strict_types=1);

namespace Tests\Feature\Rules\Catalog;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use Tests\Contracts\RuleRepositoryContract;

final class PostgreSqlRuleRepositoryContractTest extends RuleRepositoryContract
{
    private string $planId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('modules:sync')->assertExitCode(0);
        $this->planId = (string) PlanRecord::factory()->create()->id;
    }

    protected function repository(): RuleRepositoryInterface
    {
        return app(RuleRepositoryFactory::class)->make('postgresql');
    }

    protected function planId(): string
    {
        return $this->planId;
    }
}
