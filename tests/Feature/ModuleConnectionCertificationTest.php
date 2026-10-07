<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Contracts\Ports\Input\CertifyProviderConnectionPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SettingsGateway;
use Tests\TestCase;

final class ModuleConnectionCertificationTest extends TestCase
{
    use RefreshDatabase, SettingsGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway();
        config()->set('modules.sources.broker.base_url', 'https://broker.example');
        config()->set('modules.sources.broker.internal_token', 'connection-test-secret');
        config()->set('modules.sources.broker.source_service', 'mmt-ib-service');
        Http::preventStrayRequests();
    }

    public function test_both_surfaces_certify_using_the_same_effective_connection(): void
    {
        $this->artisan('settings:sync')->assertSuccessful();
        $this->artisan('modules:sync')->assertSuccessful();
        config()->set('modules.sources.broker.internal_token', 'ignored-environment-token');
        Http::fake(['https://broker.example/*' => Http::response(['success' => true, 'data' => ['status' => 'ready', 'service' => 'broker-service', 'schema_version' => 1]])]);
        $id = DB::table('modules')->where('code', 'broker')->value('id');
        $before = DB::table('modules')->where('id', $id)->first();
        $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection')->assertOk()->assertJsonPath('data.certified', true)->assertJsonPath('data.code', 'certified')->assertJsonPath('data.module_id', null);
        $this->postJson('/api/ib/v1/admin/modules/'.$id.'/certify-connection')->assertOk()->assertJsonPath('data.certified', true)->assertJsonPath('data.module_id', $id);
        Http::assertSentCount(2);
        Http::assertSent(static fn ($request): bool => $request->url() === 'https://broker.example/api/broker/v1/internal/health' && $request->method() === 'GET' && $request->hasHeader('X-Internal-Token', 'connection-test-secret') && $request->hasHeader('X-Internal-Source', 'mmt-ib-service'));
        self::assertEquals($before, DB::table('modules')->where('id', $id)->first());
    }

    public static function responses(): iterable
    {
        yield 'wrong token' => [401, [], 'authentication_rejected'];
        yield 'wrong origin' => [403, [], 'authorization_rejected'];
        yield 'missing endpoint' => [404, [], 'endpoint_missing'];
        yield 'unhealthy' => [503, [], 'unhealthy'];
        yield 'server error' => [500, [], 'unhealthy'];
        yield 'public health' => [200, ['status' => 'ok'], 'invalid_response'];
        yield 'other provider' => [200, ['success' => true, 'data' => ['status' => 'ready', 'service' => 'another-service', 'schema_version' => 1]], 'invalid_response'];
        yield 'wrong schema type' => [200, ['success' => true, 'data' => ['status' => 'ready', 'service' => 'broker-service', 'schema_version' => '1']], 'invalid_response'];
        yield 'redirect' => [302, [], 'invalid_response'];
    }

    #[DataProvider('responses')]
    public function test_failed_certification_returns_a_safe_result(int $status, array $body, string $code): void
    {
        Http::fake(['*' => Http::response($body, $status, ['Location' => 'https://elsewhere.example'])]);
        $response = $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection')->assertOk()->assertJsonPath('data.certified', false)->assertJsonPath('data.code', $code);
        self::assertStringNotContainsString('connection-test-secret', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_missing_url_or_token_does_not_send_requests(): void
    {
        config()->set('modules.sources.broker.internal_token', null);
        self::assertSame('not_configured', app(CertifyProviderConnectionPort::class)->execute('broker')->code);
        config()->set('modules.sources.broker.internal_token', 'test-token');
        config()->set('modules.sources.broker.base_url', '');
        self::assertSame('not_configured', app(CertifyProviderConnectionPort::class)->execute('broker')->code);
        Http::assertNothingSent();
    }

    public function test_connection_errors_are_classified_without_leaking_details(): void
    {
        Http::fake(static fn () => throw new ConnectionException('cURL error 28: private-url timeout'));
        self::assertSame('timeout', app(CertifyProviderConnectionPort::class)->execute('broker')->code);
        Http::swap(new Factory);
        Http::fake(static fn () => throw new ConnectionException('Private connection details'));
        self::assertSame('unreachable', app(CertifyProviderConnectionPort::class)->execute('broker')->code);
    }

    public function test_certification_rejects_unknown_references_candidates_and_missing_permissions(): void
    {
        $this->postJson('/api/ib/v1/admin/settings/modules/missing/certify-connection')->assertNotFound();
        $this->postJson('/api/ib/v1/admin/modules/01993ac2-8750-73fd-b102-ba24fb06d8bf/certify-connection')->assertNotFound();
        $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection', ['url' => 'https://candidate.example', 'token' => 'candidate'])->assertUnprocessable();
        $this->gateway(['ib.settings.read']);
        $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection')->assertForbidden();
        $this->postJson('/api/ib/v1/admin/modules/01993ac2-8750-73fd-b102-ba24fb06d8bf/certify-connection')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_both_routes_share_the_rate_limit_per_actor(): void
    {
        $this->artisan('modules:sync')->assertSuccessful();
        $id = DB::table('modules')->where('code', 'broker')->value('id');
        Http::fake(['*' => Http::response([], 401)]);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection')->assertOk();
        }
        $this->postJson('/api/ib/v1/admin/modules/'.$id.'/certify-connection')->assertStatus(429);
        Http::assertSentCount(10);
    }

    public function test_copy_trading_connection_uses_its_own_identity_and_does_not_follow_redirects(): void
    {
        config()->set('modules.sources.copy_trading.base_url', 'https://copy.example');
        config()->set('modules.sources.copy_trading.internal_token', 'copy-test-secret');
        config()->set('modules.sources.copy_trading.source_service', 'mmt-ib-service');
        Http::fake(static function (Request $request, array $options): mixed {
            self::assertFalse($options['allow_redirects']);
            self::assertSame('https://copy.example/api/copy-trading/v1/internal/health', $request->url());
            self::assertTrue($request->hasHeader('X-Internal-Token', 'copy-test-secret'));

            return Http::response(['success' => true, 'data' => ['status' => 'ready', 'service' => 'copy-trading-service', 'schema_version' => 1]]);
        });
        $this->postJson('/api/ib/v1/admin/settings/modules/copy_trading/certify-connection')->assertOk()->assertJsonPath('data.certified', true);
    }
}
