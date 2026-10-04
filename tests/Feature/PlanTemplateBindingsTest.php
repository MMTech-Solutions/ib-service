<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogItemData;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramProgressionConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramProgressionConfigurationPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class PlanTemplateBindingsTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public static function families(): array
    {
        return [['payment', 'rate'], ['progression', 'weight']];
    }

    #[DataProvider('families')]
    public function test_create_repeat_accumulate_list_and_isolate(string $family, string $value): void
    {
        $plan = $this->plan('first');
        $other = $this->plan('other');
        $version = $this->version($family, $value);
        $url = "/api/ib/v1/admin/plans/{$plan['id']}/{$family}-template-version-bindings";
        $binding = $this->gatewayJson('POST', $url, ['template_version_id' => $version])->assertCreated()->assertJsonMissingPath('data.binding')->json('data');
        self::assertSame($plan['id'], $binding['plan_id']);
        self::assertSame($version, $binding['template_version_id']);
        $this->gatewayJson('POST', $url, ['template_version_id' => $version])->assertOk()->assertExactJson(['success' => true, 'data' => $binding, 'meta' => ['message' => 'Template version bound successfully.']]);
        $this->gatewayJson('GET', "{$url}/{$binding['id']}")->assertOk()->assertJsonPath('data', $binding);
        $next = $this->version($family, $value);
        $this->gatewayJson('POST', $url, ['template_version_id' => $next])->assertCreated();
        $this->gatewayJson('GET', $url.'?per_page=1&page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $binding['id'])->assertJsonPath('meta.pagination.total', 2);
        $this->gatewayJson('GET', $url.'?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.template_version_id', $next);
        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$other['id']}/{$family}-template-version-bindings/{$binding['id']}")->assertNotFound();
        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$other['id']}/{$family}-template-version-bindings")->assertOk()->assertJsonPath('data', []);
        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}")->assertOk()->assertJsonPath('data.lock_version', $plan['lock_version']);
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan['id']}", ['lock_version' => $plan['lock_version'], 'reason' => 'Archive binding history'])->assertNoContent();
        $this->gatewayJson('GET', $url)->assertOk()->assertJsonCount(2, 'data');
        $this->gatewayJson('GET', "{$url}/{$binding['id']}")->assertOk();
        $this->gatewayJson('POST', $url, ['template_version_id' => $version])->assertConflict()->assertJsonPath('error.code', 'PLAN_ARCHIVED');
    }

    #[DataProvider('families')]
    public function test_validation_authorization_and_wrong_catalog(string $family, string $value): void
    {
        $plan = $this->plan('validation');
        $url = "/api/ib/v1/admin/plans/{$plan['id']}/{$family}-template-version-bindings";
        $draft = $this->version($family, $value, false);
        $this->gatewayJson('POST', $url, ['template_version_id' => $draft])->assertConflict()->assertJsonPath('error.code', 'TEMPLATE_VERSION_NOT_PUBLISHED');
        $opposite = $family === 'payment' ? 'progression' : 'payment';
        $foreign = $this->version($opposite, $opposite === 'payment' ? 'rate' : 'weight');
        $this->gatewayJson('POST', $url, ['template_version_id' => $foreign])->assertNotFound();
        $this->gatewayJson('POST', $url, ['template_version_id' => (string) Str::uuid7()])->assertNotFound();
        $this->gatewayJson('POST', $url, ['template_version_id' => 'invalid'])->assertUnprocessable();
        $this->gatewayJson('GET', $url.'?per_page=101')->assertUnprocessable();
        $this->gatewayJson('GET', $url.'?page=0')->assertUnprocessable();
        $this->gatewayJson('POST', $url, ['template_version_id' => $foreign, 'created_at' => '2020-01-01'])->assertUnprocessable();
        foreach (['POST', 'GET'] as $method) {
            $this->assertGatewayAuthGuards($method, $url, $method === 'POST' ? ['template_version_id' => $draft] : []);
        }
        $this->assertGatewayAuthGuards('GET', $url.'/'.Str::uuid7());
        $this->gatewayJson('GET', '/api/ib/v1/admin/plans/'.Str::uuid7()."/{$family}-template-version-bindings")->assertNotFound();
    }

    #[DataProvider('families')]
    public function test_binding_a_later_version_preserves_the_program_selection(string $family, string $value): void
    {
        $module = (string) DB::table('modules')->where('code', 'broker')->value('id');
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'consumer', 'name' => 'Consumer', 'progression_period' => 'monthly', 'module_ids' => [$module]])->assertCreated()->json('data.id');
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/programs", ['code' => 'entry', 'name' => 'Entry', 'entry_threshold' => 0, 'module_ids' => [$module]])->assertCreated()->json('data.id');
        $version = $this->version($family, $value);
        $url = "/api/ib/v1/admin/plans/{$plan}/{$family}-template-version-bindings";
        $binding = $this->gatewayJson('POST', $url, ['template_version_id' => $version])->assertCreated()->json('data.id');
        $this->app->instance(ListInstrumentCatalogPort::class, new class implements ListInstrumentCatalogPort
        {
            public function execute(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData
            {
                return new InstrumentCatalogPageData([new InstrumentCatalogItemData('broker:server_group:live:symbol:gold', 'symbol', 'Gold', [], 'USD')], 1, 1, 1);
            }
        });
        $key = "plan_{$family}_template_version_binding_id";
        $symbol = ['module_id' => $module, 'instrument_reference' => 'broker:server_group:live:symbol:gold', 'use_for_progression' => $family === 'progression', 'use_for_volume_reward' => $family === 'payment', 'use_for_cpa' => false, $key => $binding];
        if ($family === 'payment') {
            $symbol['commission_type'] = 'fixed';
            $symbol['commission_value'] = '0.1';
        }
        $configure = "/api/ib/v1/admin/plans/{$plan}/programs/{$program}/symbol-configurations";
        $selection = $this->gatewayJson('PUT', $configure, ['symbols' => [$symbol]])->assertOk()->assertJsonPath("data.0.{$key}", $binding)->json('data.0.id');
        $next = $this->version($family, $value);
        $this->gatewayJson('POST', $url, ['template_version_id' => $next])->assertCreated();
        $this->assertDatabaseHas('program_symbol_configurations', ['id' => $selection, $key => $binding, 'ends_at' => null]);
        $this->assertDatabaseHas("plan_{$family}_template_version_bindings", ['id' => $binding, 'template_version_id' => $version]);
        $this->assertDatabaseCount('program_symbol_configurations', 1);
        if ($family === 'progression') {
            $context = app(ResolveProgramProgressionConfigurationPort::class)->resolve(new ResolveProgramProgressionConfigurationQueryData($program, $module, $symbol['instrument_reference'], 0, now('UTC')->toISOString()));
            self::assertSame($version, $context->progression_template_version_id);
            self::assertSame('0.10000000', $context->distribution_weight);
        }
    }

    private function plan(string $code): array
    {
        return $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => $code, 'name' => $code, 'progression_period' => 'monthly'])->assertCreated()->json('data');
    }

    private function version(string $family, string $value, bool $publish = true): string
    {
        $template = $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates", ['name' => 'Template '.Str::uuid7()])->assertCreated()->json('data.id');
        $version = $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, $value => '0.1']]])->assertCreated()->json('data.versions.0');
        if ($publish) {
            $this->gatewayJson('POST', "/api/ib/v1/admin/{$family}-templates/{$template}/versions/{$version['id']}/publish", ['lock_version' => $version['lock_version']])->assertOk();
        }

        return $version['id'];
    }
}
