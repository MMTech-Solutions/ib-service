<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class PlanTemplateBindingRepositoryContractTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    public static function repositories(): array
    {
        return [['payment', 'rate', 'memory'], ['payment', 'rate', 'postgresql'], ['progression', 'weight', 'memory'], ['progression', 'weight', 'postgresql']];
    }

    #[DataProvider('repositories')]
    public function test_unique_identity_isolation_pagination_and_stable_order(string $family, string $value, string $driver): void
    {
        $this->seedAuthorizedAdmin();
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'contract', 'name' => 'Contract', 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $template = $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates", ['name' => 'Contract'])->assertCreated()->json('data.id');
        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, $value => '0.1']]])->assertCreated()->json('data.versions.0.id');
        $class = 'App\\Features\\Plans\\Catalog\\DTOs\\'.ucfirst($family).'TemplateBindingData';
        $repository = app("plans.{$family}-template-bindings.repositories.{$driver}");
        $original = new $class((string) Str::uuid7(), $plan, $version, '2026-10-04T00:00:00.000000Z');
        $first = $repository->createOrFind($original);
        self::assertTrue($first->created);
        $repeat = $repository->createOrFind(new $class((string) Str::uuid7(), $plan, $version, '2026-10-05T00:00:00.000000Z'));
        self::assertFalse($repeat->created);
        self::assertSame($original->toArray(), $repeat->binding->toArray());
        self::assertSame($original->toArray(), $repository->find($plan, $original->id)->toArray());
        self::assertSame($version, $repository->resolve($plan, $original->id));
        $other = (string) Str::uuid7();
        self::assertNull($repository->find($other, $original->id));
        self::assertNull($repository->resolve($other, $original->id));
        self::assertSame([], $repository->paginate($other, 1, 1)->items);
        $next = $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, $value => '0.2']]])->assertCreated()->json('data.versions.1.id');
        $second = new $class((string) Str::uuid7(), $plan, $next, $original->created_at);
        $repository->createOrFind($second);
        $page = $repository->paginate($plan, 1, 1);
        self::assertSame(2, $page->total);
        self::assertSame(1, $page->perPage);
        self::assertSame($original->id, $page->items[0]->id);
        self::assertSame($second->id, $repository->paginate($plan, 2, 1)->items[0]->id);
        self::assertSame([], $repository->paginate($plan, 3, 1)->items);
    }
}
