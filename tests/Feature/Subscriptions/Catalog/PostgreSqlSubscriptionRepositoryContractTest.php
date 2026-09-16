<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions\Catalog;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Subscriptions\Catalog\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use Illuminate\Support\Str;
use Tests\Contracts\SubscriptionRepositoryContract;

final class PostgreSqlSubscriptionRepositoryContractTest extends SubscriptionRepositoryContract
{
    private string $planId;

    private string $programId;

    private string $alternateProgramId;

    private string $foreignProgramId;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = PlanRecord::factory()->create();
        $foreignPlan = PlanRecord::factory()->create([
            'code' => 'foreign-'.Str::lower(Str::random(8)),
        ]);

        $program = ProgramRecord::factory()->create([
            'plan_id' => $plan->id,
            'code' => 'basic',
            'position' => 1,
            'entry_threshold' => 0,
        ]);
        $alternate = ProgramRecord::factory()->create([
            'plan_id' => $plan->id,
            'code' => 'advanced',
            'position' => 2,
            'entry_threshold' => 100,
        ]);
        $foreign = ProgramRecord::factory()->create([
            'plan_id' => $foreignPlan->id,
            'code' => 'other',
            'position' => 1,
            'entry_threshold' => 0,
        ]);

        $this->planId = (string) $plan->id;
        $this->programId = (string) $program->id;
        $this->alternateProgramId = (string) $alternate->id;
        $this->foreignProgramId = (string) $foreign->id;
    }

    protected function repository(): SubscriptionRepositoryInterface
    {
        return app(SubscriptionRepositoryFactory::class)->make('postgresql');
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function alternateProgramId(): string
    {
        return $this->alternateProgramId;
    }

    protected function foreignProgramId(): string
    {
        return $this->foreignProgramId;
    }
}
