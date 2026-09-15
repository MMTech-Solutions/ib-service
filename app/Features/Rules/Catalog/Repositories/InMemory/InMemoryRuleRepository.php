<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Repositories\InMemory;

use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugException;
use App\Features\Rules\Catalog\Exceptions\RuleConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionImmutableException;
use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;
use Closure;
use Throwable;

final class InMemoryRuleRepository implements RuleRepositoryInterface
{
    /** @var array<string, Rule> */
    private array $rules = [];

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->rules), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->rules = $snapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?Rule
    {
        $rule = $this->rules[$id] ?? null;

        return $rule === null ? null : $this->copy($rule);
    }

    public function findByPlanAndId(string $planId, string $ruleId): ?Rule
    {
        $rule = $this->findById($ruleId);
        if ($rule === null || $rule->planId !== $planId) {
            return null;
        }

        return $rule;
    }

    public function listByPlanId(string $planId): array
    {
        $rules = array_values(array_filter(
            $this->rules,
            static fn (Rule $rule): bool => $rule->planId === $planId,
        ));
        usort($rules, static fn (Rule $a, Rule $b): int => [$a->name, $a->id] <=> [$b->name, $b->id]);

        return array_map(fn (Rule $rule): Rule => $this->copy($rule), $rules);
    }

    public function create(Rule $rule): void
    {
        $this->assertUniqueIdentity($rule, null);
        $this->rules[$rule->id] = $this->copy($rule);
    }

    public function update(Rule $rule, int $expectedLockVersion): void
    {
        $stored = $this->rules[$rule->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw RuleConcurrencyException::forRule($rule->id);
        }

        $this->assertUniqueIdentity($rule, $rule->id);
        $rule->lockVersion = $expectedLockVersion + 1;
        $rule->versions = $stored->versions;
        $this->rules[$rule->id] = $this->copy($rule);
    }

    public function nextVersionNumber(string $ruleId): int
    {
        $rule = $this->rules[$ruleId] ?? null;
        if ($rule === null) {
            return 1;
        }

        $max = 0;
        foreach ($rule->versions as $version) {
            if ($version->versionNumber > $max) {
                $max = $version->versionNumber;
            }
        }

        return $max + 1;
    }

    public function addVersion(Rule $rule, RuleVersion $version): void
    {
        $stored = $this->rules[$rule->id] ?? null;
        if ($stored === null) {
            throw RuleConcurrencyException::forRule($rule->id);
        }

        $stored->addVersion($this->copyVersion($version), $rule->updatedAt);
        $this->rules[$rule->id] = $this->copy($stored);
        $rule->versions = $this->copy($stored)->versions;
    }

    public function updateVersion(RuleVersion $version, int $expectedLockVersion): void
    {
        foreach ($this->rules as $ruleId => $rule) {
            $storedVersion = $rule->findVersion($version->id);
            if ($storedVersion === null) {
                continue;
            }

            if ($storedVersion->lockVersion !== $expectedLockVersion) {
                throw RuleVersionConcurrencyException::forVersion($version->id);
            }

            if (! $storedVersion->isDraft() && $version->isDraft()) {
                throw RuleVersionImmutableException::forId($version->id);
            }

            $version->lockVersion = $expectedLockVersion + 1;
            $copies = [];
            foreach ($rule->versions as $current) {
                $copies[] = $current->id === $version->id
                    ? $this->copyVersion($version)
                    : $this->copyVersion($current);
            }
            $rule->versions = $copies;
            $this->rules[$ruleId] = $this->copy($rule);

            return;
        }

        throw RuleVersionConcurrencyException::forVersion($version->id);
    }

    private function assertUniqueIdentity(Rule $rule, ?string $ignoreId): void
    {
        foreach ($this->rules as $stored) {
            if ($ignoreId !== null && $stored->id === $ignoreId) {
                continue;
            }

            if ($stored->planId !== $rule->planId) {
                continue;
            }

            if ($stored->name === $rule->name) {
                throw DuplicateRuleNameException::forName($rule->planId, $rule->name);
            }

            if ($stored->slug === $rule->slug) {
                throw DuplicateRuleSlugException::forSlug($rule->planId, $rule->slug);
            }
        }
    }

    private function copy(Rule $rule): Rule
    {
        /** @var Rule $copy */
        $copy = unserialize(serialize($rule), ['allowed_classes' => true]);

        return $copy;
    }

    private function copyVersion(RuleVersion $version): RuleVersion
    {
        /** @var RuleVersion $copy */
        $copy = unserialize(serialize($version), ['allowed_classes' => true]);

        return $copy;
    }
}
