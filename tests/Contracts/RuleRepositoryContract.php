<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Rules\Catalog\Contracts\Repositories\RuleRepositoryInterface;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleNameException;
use App\Features\Rules\Catalog\Exceptions\DuplicateRuleSlugException;
use App\Features\Rules\Catalog\Exceptions\RuleConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionConcurrencyException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionImmutableException;
use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class RuleRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): RuleRepositoryInterface;

    abstract protected function planId(): string;

    public function test_it_creates_lists_and_recovers_a_rule(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);

        $stored = $repository->findByPlanAndId($this->planId(), $rule->id);
        self::assertNotNull($stored);
        self::assertSame('CPA Standard', $stored->name);
        self::assertSame('cpa-standard', $stored->slug);
        self::assertSame(RuleStrategyType::PointsPerQuantityUnit->value, $stored->strategyType);

        $listed = $repository->listByPlanId($this->planId());
        self::assertCount(1, $listed);
        self::assertSame($rule->id, $listed[0]->id);
    }

    public function test_it_rejects_duplicate_names_within_the_same_plan(): void
    {
        $repository = $this->repository();
        $repository->create($this->rule('CPA Standard'));

        $this->expectException(DuplicateRuleNameException::class);
        $repository->create($this->rule('CPA Standard'));
    }

    public function test_it_rejects_duplicate_slugs_within_the_same_plan(): void
    {
        $repository = $this->repository();
        $repository->create($this->rule('CPA Standard'));

        $this->expectException(DuplicateRuleSlugException::class);
        $repository->create($this->rule('CPA  Standard'));
    }

    public function test_it_keeps_slug_stable_when_renaming(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);

        $writer = $repository->findById($rule->id);
        self::assertNotNull($writer);
        $writer->updateAdministrativeFields('Volume CPA', 'Updated', $this->now());
        $repository->update($writer, 1);

        $stored = $repository->findById($rule->id);
        self::assertNotNull($stored);
        self::assertSame('Volume CPA', $stored->name);
        self::assertSame('cpa-standard', $stored->slug);
        self::assertSame(2, $stored->lockVersion);
    }

    public function test_it_rejects_obsolete_lock_versions(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);

        $writer = $repository->findById($rule->id);
        $stale = $repository->findById($rule->id);
        self::assertNotNull($writer);
        self::assertNotNull($stale);

        $writer->updateAdministrativeFields('CPA Plus', null, $this->now());
        $repository->update($writer, 1);

        $stale->updateAdministrativeFields('Stale', null, $this->now());
        $this->expectException(RuleConcurrencyException::class);
        $repository->update($stale, 1);
    }

    public function test_it_creates_and_publishes_versions_without_selecting_current(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);

        $first = $this->draft($rule->id, $repository->nextVersionNumber($rule->id), '50.00');
        $repository->addVersion($rule, $first);
        $first->publish($this->now());
        $repository->updateVersion($first, 1);

        $second = $this->draft($rule->id, $repository->nextVersionNumber($rule->id), '75.00');
        $repository->addVersion($rule, $second);
        $second->publish($this->now());
        $repository->updateVersion($second, 1);

        $stored = $repository->findById($rule->id);
        self::assertNotNull($stored);
        self::assertCount(2, $stored->versions);
        self::assertSame(RuleVersionStatus::Published, $stored->versions[0]->status);
        self::assertSame(RuleVersionStatus::Published, $stored->versions[1]->status);
        self::assertSame(1, $stored->versions[0]->versionNumber);
        self::assertSame(2, $stored->versions[1]->versionNumber);
    }

    public function test_it_rejects_editing_a_published_version(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);
        $version = $this->draft($rule->id, 1, '50.00');
        $repository->addVersion($rule, $version);
        $version->publish($this->now());
        $repository->updateVersion($version, 1);

        $stored = $repository->findById($rule->id);
        self::assertNotNull($stored);
        $published = $stored->findVersion($version->id);
        self::assertNotNull($published);

        $this->expectException(RuleVersionImmutableException::class);
        $published->replaceConfiguration(1, [
            'unit' => 'lot',
            'points_per_unit' => '80.00',
        ], $this->now());
    }

    public function test_it_rejects_stale_version_lock(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);
        $version = $this->draft($rule->id, 1, '50.00');
        $repository->addVersion($rule, $version);

        $writer = $repository->findById($rule->id)?->findVersion($version->id);
        $stale = $repository->findById($rule->id)?->findVersion($version->id);
        self::assertNotNull($writer);
        self::assertNotNull($stale);

        $writer->replaceConfiguration(1, [
            'unit' => 'lot',
            'points_per_unit' => '60.00',
        ], $this->now());
        $repository->updateVersion($writer, 1);

        $stale->replaceConfiguration(1, [
            'unit' => 'lot',
            'points_per_unit' => '70.00',
        ], $this->now());
        $this->expectException(RuleVersionConcurrencyException::class);
        $repository->updateVersion($stale, 1);
    }

    public function test_transaction_rolls_back_after_an_error(): void
    {
        $repository = $this->repository();
        $rule = $this->rule('CPA Standard');
        $repository->create($rule);

        try {
            $repository->transaction(function () use ($repository, $rule): void {
                $rule->updateAdministrativeFields('Changed', null, $this->now());
                $repository->update($rule, 1);
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $stored = $repository->findById($rule->id);
        self::assertNotNull($stored);
        self::assertSame('CPA Standard', $stored->name);
        self::assertSame(1, $stored->lockVersion);
    }

    protected function rule(string $name): Rule
    {
        return Rule::create(
            id: (string) Str::uuid7(),
            planId: $this->planId(),
            name: $name,
            description: null,
            strategyType: RuleStrategyType::PointsPerQuantityUnit->value,
            now: $this->now(),
        );
    }

    protected function draft(string $ruleId, int $versionNumber, string $points): RuleVersion
    {
        return RuleVersion::draft(
            id: (string) Str::uuid7(),
            ruleId: $ruleId,
            versionNumber: $versionNumber,
            schemaVersion: 1,
            configuration: [
                'unit' => 'lot',
                'points_per_unit' => $points,
            ],
            now: $this->now(),
        );
    }

    protected function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
