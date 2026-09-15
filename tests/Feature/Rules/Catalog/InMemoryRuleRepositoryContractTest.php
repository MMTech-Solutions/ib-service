<?php

declare(strict_types=1);

namespace Tests\Feature\Rules\Catalog;

use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use App\Features\Rules\Catalog\Repositories\InMemory\InMemoryRuleRepository;
use Illuminate\Support\Str;
use Tests\Contracts\RuleRepositoryContract;

final class InMemoryRuleRepositoryContractTest extends RuleRepositoryContract
{
    private InMemoryRuleRepository $ruleRepository;

    private string $planId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleRepository = new InMemoryRuleRepository;
        $this->planId = (string) Str::uuid7();
    }

    protected function repository(): RuleRepositoryInterface
    {
        return $this->ruleRepository;
    }

    protected function planId(): string
    {
        return $this->planId;
    }
}
