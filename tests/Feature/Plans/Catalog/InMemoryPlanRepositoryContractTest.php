<?php

declare(strict_types=1);

namespace Tests\Feature\Plans\Catalog;

use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use App\Features\Plans\Catalog\Repositories\InMemory\InMemoryPlanRepository;
use Illuminate\Support\Str;
use Tests\Contracts\PlanRepositoryContract;

final class InMemoryPlanRepositoryContractTest extends PlanRepositoryContract
{
    private InMemoryPlanRepository $planRepository;

    /** @var list<string> */
    private array $moduleIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->planRepository = new InMemoryPlanRepository;
        $this->moduleIds = [(string) Str::uuid7(), (string) Str::uuid7()];
    }

    protected function repository(): PlanRepositoryInterface
    {
        return $this->planRepository;
    }

    protected function moduleIds(): array
    {
        return $this->moduleIds;
    }
}
