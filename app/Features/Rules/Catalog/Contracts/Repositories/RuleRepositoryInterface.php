<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Contracts\Repositories;

use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;
use Closure;

interface RuleRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?Rule;

    public function findByPlanAndId(string $planId, string $ruleId): ?Rule;

    /** @return list<Rule> */
    public function listByPlanId(string $planId): array;

    public function create(Rule $rule): void;

    public function update(Rule $rule, int $expectedLockVersion): void;

    public function nextVersionNumber(string $ruleId): int;

    public function addVersion(Rule $rule, RuleVersion $version): void;

    public function updateVersion(RuleVersion $version, int $expectedLockVersion): void;
}
