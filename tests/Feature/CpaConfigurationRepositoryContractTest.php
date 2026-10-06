<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rules\Assignments\Factories\CpaConfigurationRepositoryFactory;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CpaFixtures;
use Tests\TestCase;

final class CpaConfigurationRepositoryContractTest extends TestCase
{
    use CpaFixtures, RefreshDatabase;

    public static function drivers(): array
    {
        return [['memory'], ['postgresql']];
    }

    #[DataProvider('drivers')]
    public function test_historical_association_contract(string $driver): void
    {
        $f = $this->cpaFixture(twoModules: false);
        $at = CarbonImmutable::now('UTC')->startOfSecond();
        $actor = (string) Str::uuid7();
        $secondId = (string) Str::uuid7();
        RuleVersionRecord::query()->create(['id' => $secondId, 'rule_id' => $f['rule']->id, 'version_number' => 2, 'status' => 'published', 'schema_version' => 1, 'configuration' => [...$f['configuration'], 'amount' => '50.00'], 'published_at' => $at, 'lock_version' => 1, 'created_at' => $at, 'updated_at' => $at]);
        $rule = app(RuleRepositoryFactory::class)->make('postgresql')->findById($f['rule']->id);
        app(RuleRepositoryFactory::class)->make('memory')->create($rule);
        $repository = app(CpaConfigurationRepositoryFactory::class)->make($driver);
        $repository->withdraw($f['program']->id, $actor, $at->toISOString());
        self::assertNull($repository->current($f['program']->id));
        self::assertNotNull($repository->publishedVersion($f['plan']->id, $secondId));
        self::assertNull($repository->publishedVersion((string) Str::uuid7(), $secondId));
        $first = $repository->replace($f['program']->id, $f['rule']->id, $f['version']->id, $actor, $at->addSecond()->toISOString());
        self::assertSame($first->id, $repository->replace($f['program']->id, $f['rule']->id, $f['version']->id, $actor, $at->addSeconds(2)->toISOString())->id);
        $second = $repository->replace($f['program']->id, $f['rule']->id, $secondId, $actor, $at->addSeconds(3)->toISOString());
        self::assertNotSame($first->id, $second->id);
        self::assertSame($f['version']->id, $repository->resolve($f['program']->id, $at->addSeconds(2)->toISOString())->rule_version_id);
        self::assertSame($secondId, $repository->resolve($f['program']->id, $at->addSeconds(3)->toISOString())->rule_version_id);
        $repository->withdraw($f['program']->id, $actor, $at->addSeconds(4)->toISOString());
        $repository->withdraw($f['program']->id, $actor, $at->addSeconds(5)->toISOString());
        self::assertNull($repository->current($f['program']->id));
        self::assertFalse($repository->resolve($f['program']->id, $at->addSeconds(4)->toISOString())->found());
    }
}
