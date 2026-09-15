<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Exceptions\InvalidRuleSlugException;
use App\Features\Rules\Catalog\Exceptions\RuleVersionImmutableException;
use App\Features\Rules\Catalog\Models\Rule;
use App\Features\Rules\Catalog\Models\RuleVersion;
use App\Features\Rules\Catalog\Support\RuleSlug;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class RuleStateTransitionsTest extends TestCase
{
    public function test_it_generates_a_stable_slug_and_ignores_noop_updates(): void
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $rule = Rule::create(
            id: (string) Str::uuid7(),
            planId: (string) Str::uuid7(),
            name: 'CPA Estándar',
            description: null,
            strategyType: RuleStrategyType::PointsPerQuantityUnit->value,
            now: $now,
        );

        self::assertSame('cpa-estandar', $rule->slug);
        self::assertFalse($rule->updateAdministrativeFields('CPA Estándar', null, $now));
        self::assertTrue($rule->updateAdministrativeFields('Volume CPA', 'Updated', $now));
        self::assertSame('cpa-estandar', $rule->slug);
    }

    public function test_it_rejects_names_that_cannot_produce_a_slug(): void
    {
        $this->expectException(InvalidRuleSlugException::class);
        RuleSlug::fromName('!!!');
    }

    public function test_it_publishes_a_draft_and_rejects_later_edits(): void
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $version = RuleVersion::draft(
            id: (string) Str::uuid7(),
            ruleId: (string) Str::uuid7(),
            versionNumber: 1,
            schemaVersion: 1,
            configuration: [
                'unit' => 'lot',
                'points_per_unit' => '50.00',
            ],
            now: $now,
        );

        self::assertTrue($version->isDraft());
        $version->publish($now);
        self::assertTrue($version->isPublished());
        $this->expectException(RuleVersionImmutableException::class);
        $version->replaceConfiguration(1, [
            'unit' => 'lot',
            'points_per_unit' => '80.00',
        ], $now);
    }
}
