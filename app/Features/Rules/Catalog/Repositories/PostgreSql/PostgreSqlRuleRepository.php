<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Repositories\PostgreSql;

use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugException;
use App\Features\Rules\Catalog\Exceptions\RuleConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionImmutableException;
use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final class PostgreSqlRuleRepository implements RuleRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?Rule
    {
        $record = RuleRecord::query()->with('versions')->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByPlanAndId(string $planId, string $ruleId): ?Rule
    {
        $record = RuleRecord::query()
            ->with('versions')
            ->whereKey($ruleId)
            ->where('plan_id', $planId)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function listByPlanId(string $planId): array
    {
        return RuleRecord::query()
            ->with('versions')
            ->where('plan_id', $planId)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (RuleRecord $record): Rule => $this->hydrate($record))
            ->all();
    }

    public function create(Rule $rule): void
    {
        try {
            RuleRecord::query()->create($this->ruleAttributes($rule));
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->duplicateIdentityException($exception, $rule);
        }
    }

    public function update(Rule $rule, int $expectedLockVersion): void
    {
        $nextLockVersion = $expectedLockVersion + 1;

        try {
            $affected = RuleRecord::query()
                ->whereKey($rule->id)
                ->where('lock_version', $expectedLockVersion)
                ->update([
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'lock_version' => $nextLockVersion,
                    'updated_at' => $rule->updatedAt,
                ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw $this->duplicateIdentityException($exception, $rule);
        }

        if ($affected !== 1) {
            throw RuleConcurrencyException::forRule($rule->id);
        }

        $rule->lockVersion = $nextLockVersion;
    }

    public function nextVersionNumber(string $ruleId): int
    {
        RuleRecord::query()->whereKey($ruleId)->lockForUpdate()->firstOrFail();
        $max = RuleVersionRecord::query()->where('rule_id', $ruleId)->max('version_number');

        return ((int) $max) + 1;
    }

    public function addVersion(Rule $rule, RuleVersion $version): void
    {
        RuleRecord::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
        RuleVersionRecord::query()->create($this->versionAttributes($version));
        RuleRecord::query()->whereKey($rule->id)->update(['updated_at' => $rule->updatedAt]);
    }

    public function updateVersion(RuleVersion $version, int $expectedLockVersion): void
    {
        $affected = RuleVersionRecord::query()
            ->whereKey($version->id)
            ->where('lock_version', $expectedLockVersion)
            ->where('status', RuleVersionStatus::Draft->value)
            ->update($this->versionUpdateAttributes($version, $expectedLockVersion + 1));

        if ($affected !== 1) {
            $current = RuleVersionRecord::query()->whereKey($version->id)->first();
            if ($current !== null && (string) $current->status === RuleVersionStatus::Published->value) {
                throw RuleVersionImmutableException::forId($version->id);
            }

            throw RuleVersionConcurrencyException::forVersion($version->id);
        }

        $version->lockVersion = $expectedLockVersion + 1;
    }

    private function hydrate(RuleRecord $record): Rule
    {
        return new Rule(
            id: (string) $record->id,
            planId: (string) $record->plan_id,
            name: (string) $record->name,
            slug: (string) $record->slug,
            description: $record->description === null ? null : (string) $record->description,
            strategyType: (string) $record->strategy_type,
            lockVersion: (int) $record->lock_version,
            versions: $record->versions->map(
                static fn (RuleVersionRecord $version): RuleVersion => new RuleVersion(
                    id: (string) $version->id,
                    ruleId: (string) $version->rule_id,
                    versionNumber: (int) $version->version_number,
                    status: RuleVersionStatus::from((string) $version->status),
                    schemaVersion: (int) $version->schema_version,
                    configuration: is_array($version->configuration) ? $version->configuration : [],
                    publishedAt: $version->published_at?->utc()->toISOString(),
                    lockVersion: (int) $version->lock_version,
                    createdAt: $version->created_at->utc()->toISOString(),
                    updatedAt: $version->updated_at->utc()->toISOString(),
                )
            )->all(),
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function ruleAttributes(Rule $rule): array
    {
        return [
            'id' => $rule->id,
            'plan_id' => $rule->planId,
            'name' => $rule->name,
            'slug' => $rule->slug,
            'description' => $rule->description,
            'strategy_type' => $rule->strategyType,
            'lock_version' => $rule->lockVersion,
            'created_at' => $rule->createdAt,
            'updated_at' => $rule->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function versionAttributes(RuleVersion $version): array
    {
        return [
            'id' => $version->id,
            'rule_id' => $version->ruleId,
            'version_number' => $version->versionNumber,
            'status' => $version->status->value,
            'schema_version' => $version->schemaVersion,
            'configuration' => $version->configuration,
            'published_at' => $version->publishedAt,
            'lock_version' => $version->lockVersion,
            'created_at' => $version->createdAt,
            'updated_at' => $version->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function versionUpdateAttributes(RuleVersion $version, int $nextLockVersion): array
    {
        return [
            'status' => $version->status->value,
            'schema_version' => $version->schemaVersion,
            'configuration' => $version->configuration,
            'published_at' => $version->publishedAt,
            'lock_version' => $nextLockVersion,
            'updated_at' => $version->updatedAt,
        ];
    }

    private function duplicateIdentityException(UniqueConstraintViolationException $exception, Rule $rule): RuntimeException
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'rules_plan_id_slug_unique') || str_contains($message, '(plan_id, slug)')) {
            return DuplicateRuleSlugException::forSlug($rule->planId, $rule->slug);
        }

        return DuplicateRuleNameException::forName($rule->planId, $rule->name);
    }
}
