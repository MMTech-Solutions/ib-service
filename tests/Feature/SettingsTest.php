<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\Features\Settings\Services\SettingValueValidator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SettingsGateway;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use RefreshDatabase, SettingsGateway;

    public function test_catalog_listing_is_grouped_and_does_not_synchronize(): void
    {
        $this->gateway();
        $response = $this->getJson('/api/ib/v1/admin/settings?domain=Modules&section=Connections&provider=broker&per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.pagination.total', 6);
        $response->assertJsonPath('data.0.domain', 'Modules')->assertJsonPath('data.0.provider', 'broker')->assertJsonPath('data.0.persisted', false)->assertJsonPath('data.0.source', 'fallback')->assertJsonPath('meta.filters.domain', 'Modules');
        self::assertSame(0, DB::table('settings')->count());
        $this->getJson('/api/ib/v1/admin/settings?search=missing')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/ib/v1/admin/settings?per_page=101')->assertUnprocessable();
    }

    public function test_sync_copies_fallback_preserves_customization_and_reset_follows_config(): void
    {
        $this->gateway();
        config()->set('rewards.negative_pnl.enabled', true);
        $this->artisan('settings:sync', ['--dry-run' => true])->assertSuccessful();
        self::assertSame(0, DB::table('settings')->count());
        $this->artisan('settings:sync')->assertSuccessful();
        $count = DB::table('settings')->count();
        self::assertSame(count(app(SettingDefinitionRegistry::class)->all()), $count);
        config()->set('rewards.negative_pnl.enabled', false);
        self::assertTrue(app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.enabled'])->get('rewards.negative_pnl.enabled'));
        $this->patchJson('/api/ib/v1/admin/settings/rewards.negative_pnl.enabled', ['value' => false, 'lock_version' => 1, 'reason' => 'Pause generation'])->assertOk()->assertJsonPath('data.effective_value', false)->assertJsonPath('data.lock_version', 2);
        $this->artisan('settings:sync')->assertSuccessful();
        $this->getJson('/api/ib/v1/admin/settings/rewards.negative_pnl.enabled')->assertOk()->assertJsonPath('data.effective_value', false)->assertJsonPath('data.lock_version', 2);
        $this->postJson('/api/ib/v1/admin/settings/rewards.negative_pnl.enabled/reset', ['lock_version' => 2, 'reason' => 'Restore environment'])->assertOk()->assertJsonPath('data.mode', 'fallback');
        config()->set('rewards.negative_pnl.enabled', true);
        $this->artisan('settings:sync')->assertSuccessful();
        $this->getJson('/api/ib/v1/admin/settings/rewards.negative_pnl.enabled')->assertJsonPath('data.effective_value', true)->assertJsonPath('data.mode', 'fallback');
        self::assertSame($count, DB::table('settings')->count());
        self::assertSame(self::SETTINGS_ACTOR, DB::table('setting_audits')->where('action', 'update')->value('actor'));
    }

    public function test_sync_updates_definitions_and_prunes_without_overwriting_values(): void
    {
        $definition = new SettingDefinitionData('rewards.test', 'Rewards', 'General', null, 0, 'Test', 'Original', 'integer', minimum: 0);
        config()->set('rewards.test', 0);
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$definition]));
        $this->artisan('settings:sync')->assertSuccessful();
        config()->set('rewards.test', 25);
        $changed = new SettingDefinitionData('rewards.test', 'Rewards', 'General', null, 0, 'New label', 'New definition', 'integer', minimum: 0);
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$changed]));
        $this->artisan('settings:sync')->assertSuccessful();
        $record = app(SettingRepositoryInterface::class)->records()['rewards.test'];
        self::assertSame(0, $record->value);
        self::assertSame('New label', $record->definition->name);
        self::assertSame(2, $record->lock_version);
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([]));
        $this->artisan('settings:sync')->assertSuccessful();
        self::assertSame(0, DB::table('settings')->count());
        self::assertSame(1, DB::table('setting_audits')->where('action', 'sync_delete')->count());
    }

    public function test_incompatible_definition_aborts_the_entire_sync_and_duplicate_catalog_fails(): void
    {
        $original = new SettingDefinitionData('rewards.test', 'Rewards', 'General', null, 0, 'Test', 'Integer', 'integer');
        config()->set('rewards.test', 4);
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$original]));
        $this->artisan('settings:sync')->assertSuccessful();
        $incompatible = new SettingDefinitionData('rewards.test', 'Rewards', 'General', null, 0, 'Test', 'Boolean', 'boolean');
        $new = new SettingDefinitionData('rewards.other', 'Rewards', 'General', null, 0, 'Other', 'New', 'string');
        config()->set('rewards.other', 'value');
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$new, $incompatible]));
        $this->artisan('settings:sync')->assertExitCode(1);
        self::assertSame(1, DB::table('settings')->count());
        self::assertSame(1, DB::table('setting_audits')->count());
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$original, $original]));
        $this->artisan('settings:sync')->assertExitCode(1);
    }

    public function test_api_enforces_sync_version_types_and_no_arbitrary_creation(): void
    {
        $this->gateway();
        $path = '/api/ib/v1/admin/settings/rewards.negative_pnl.enabled';
        $this->patchJson($path, ['value' => false, 'lock_version' => 1, 'reason' => 'Change'])->assertConflict();
        $this->getJson('/api/ib/v1/admin/settings/unknown.key')->assertNotFound();
        $this->postJson('/api/ib/v1/admin/settings', ['key' => 'unknown.key'])->assertStatus(405);
        $this->artisan('settings:sync')->assertSuccessful();
        $this->patchJson($path, ['value' => 'false', 'lock_version' => 1, 'reason' => 'Change'])->assertUnprocessable();
        $this->patchJson($path, ['value' => false, 'lock_version' => 2, 'reason' => 'Change'])->assertConflict();
        $this->patchJson($path, ['value' => false, 'lock_version' => 1, 'reason' => 'Change', 'definition' => []])->assertUnprocessable();
        $this->postJson($path.'/reset', ['lock_version' => 1, 'reason' => ''])->assertUnprocessable();
    }

    public function test_secrets_are_encrypted_hidden_and_require_additional_permission(): void
    {
        $this->gateway();
        config()->set('modules.sources.broker.internal_token', 'fallback-test-secret');
        $this->artisan('settings:sync')->assertSuccessful();
        $path = '/api/ib/v1/admin/settings/modules.sources.broker.internal_token';
        $this->patchJson($path, ['value' => 'custom-test-secret', 'lock_version' => 1, 'reason' => 'Rotate credential'])->assertOk()->assertJsonMissingPath('data.effective_value')->assertJsonMissingPath('data.stored_value')->assertJsonPath('data.configured', true);
        $raw = (string) DB::table('settings')->where('key', 'modules.sources.broker.internal_token')->value('value');
        self::assertStringNotContainsString('custom-test-secret', $raw);
        $audit = json_encode(DB::table('setting_audits')->get(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('custom-test-secret', $audit);
        self::assertStringNotContainsString('fallback-test-secret', $audit);
        self::assertSame('custom-test-secret', app(ResolveSettingsPort::class)->execute(['modules.sources.broker.internal_token'])->get('modules.sources.broker.internal_token'));
        $this->getJson($path)->assertOk()->assertJsonMissingPath('data.effective_value');
        $this->gateway(['ib.settings.manage', 'ib.settings.read']);
        $this->patchJson($path, ['value' => 'rejected-secret', 'lock_version' => 2, 'reason' => 'Rotate'])->assertForbidden();
        $this->postJson($path.'/reset', ['lock_version' => 2, 'reason' => 'Reset'])->assertForbidden();
    }

    public function test_absent_and_reset_values_keep_null_false_and_empty_distinct(): void
    {
        $this->gateway();
        config()->set('modules.sources.copy_trading.base_url', '');
        config()->set('modules.sources.copy_trading.internal_token', null);
        config()->set('rewards.negative_pnl.enabled', false);
        $snapshot = app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.base_url', 'modules.sources.copy_trading.internal_token', 'rewards.negative_pnl.enabled']);
        self::assertSame('', $snapshot->get('modules.sources.copy_trading.base_url'));
        self::assertNull($snapshot->get('modules.sources.copy_trading.internal_token'));
        self::assertFalse($snapshot->get('rewards.negative_pnl.enabled'));
        $this->artisan('settings:sync')->assertSuccessful();
        config()->set('modules.sources.copy_trading.internal_token', 'changed');
        self::assertNull(app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.internal_token'])->get('modules.sources.copy_trading.internal_token'));
        $this->postJson('/api/ib/v1/admin/settings/modules.sources.copy_trading.internal_token/reset', ['lock_version' => 1, 'reason' => 'Fallback'])->assertOk();
        self::assertSame('changed', app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.internal_token'])->get('modules.sources.copy_trading.internal_token'));
    }

    public function test_consumer_uses_saved_url_and_secret_instead_of_config(): void
    {
        $this->gateway();
        config()->set('modules.sources.broker.internal_token', 'test-broker-secret');
        $this->artisan('settings:sync')->assertSuccessful();
        $this->patchJson('/api/ib/v1/admin/settings/modules.sources.broker.base_url', ['value' => 'https://saved.example', 'lock_version' => 1, 'reason' => 'Connect provider'])->assertOk();
        config()->set('modules.sources.broker.internal_token', 'ignored-env-secret');
        Http::fake(['https://saved.example/*' => Http::response(['data' => []])]);
        app(BrokerInstrumentCatalogApiClient::class)->list('symbols', []);
        Http::assertSent(static fn ($request): bool => str_starts_with($request->url(), 'https://saved.example/') && $request->hasHeader('X-Internal-Token', 'test-broker-secret'));
    }

    public function test_unauthenticated_and_unprivileged_calls_are_rejected(): void
    {
        $this->getJson('/api/ib/v1/admin/settings')->assertUnauthorized();
        $this->gateway([]);
        $this->getJson('/api/ib/v1/admin/settings')->assertForbidden();
        $this->postJson('/api/ib/v1/admin/settings/modules/broker/certify-connection')->assertForbidden();
    }

    public function test_invalid_ciphertext_and_database_errors_do_not_trigger_fallback(): void
    {
        config()->set('finance.internal_token', 'test-finance-secret');
        $this->artisan('settings:sync')->assertSuccessful();
        DB::table('settings')->where('key', 'finance.internal_token')->update(['value' => 'invalid-ciphertext']);
        $this->expectException(DecryptException::class);
        app(ResolveSettingsPort::class)->execute(['finance.internal_token']);
    }

    public function test_empty_values_are_preserved_by_http_without_implicit_null_conversion(): void
    {
        $this->gateway();
        $this->artisan('settings:sync')->assertSuccessful();
        $path = '/api/ib/v1/admin/settings/modules.sources.copy_trading.base_url';
        $this->patchJson($path, ['value' => '', 'lock_version' => 1, 'reason' => 'Unconfigure connection'])->assertOk()->assertJsonPath('data.effective_value', '')->assertJsonPath('data.configured', false);
        self::assertSame('', app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.base_url'])->get('modules.sources.copy_trading.base_url'));
    }

    public function test_api_cannot_update_definition_before_sync_and_hides_previous_secret(): void
    {
        $this->gateway();
        config()->set('finance.internal_token', 'classified-test-value');
        $this->artisan('settings:sync')->assertSuccessful();
        $definitions = array_map(static function (SettingDefinitionData $definition): SettingDefinitionData {
            return $definition->key === 'finance.internal_token' ? SettingDefinitionData::from([...$definition->toArray(), 'sensitive' => false, 'name' => 'Changed definition']) : $definition;
        }, app(SettingDefinitionRegistry::class)->all());
        app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry($definitions));
        $path = '/api/ib/v1/admin/settings/finance.internal_token';
        $this->getJson($path)->assertOk()->assertJsonMissingPath('data.effective_value')->assertJsonPath('data.sensitive', true);
        $this->patchJson($path, ['value' => 'replacement', 'lock_version' => 1, 'reason' => 'Update'])->assertConflict();
        $this->postJson($path.'/reset', ['lock_version' => 1, 'reason' => 'Reset'])->assertConflict();
    }

    public function test_database_failure_never_silently_uses_fallback(): void
    {
        DB::statement('ALTER TABLE settings RENAME TO settings_unavailable');
        $this->expectException(QueryException::class);
        app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.enabled']);
    }

    public function test_snapshot_keeps_captured_values_after_a_later_change(): void
    {
        config()->set('rewards.minimum_amount_major', '0.01');
        $snapshot = app(ResolveSettingsPort::class)->execute(['rewards.minimum_amount_major']);
        config()->set('rewards.minimum_amount_major', '10.00');
        self::assertSame('0.01', $snapshot->get('rewards.minimum_amount_major'));
        self::assertSame('10.00', app(ResolveSettingsPort::class)->execute(['rewards.minimum_amount_major'])->get('rewards.minimum_amount_major'));
    }

    public function test_cached_configuration_does_not_freeze_database_values(): void
    {
        $this->gateway();
        $repository = Env::getRepository();
        $previous = $repository->get('APP_CONFIG_CACHE');
        $path = tempnam(sys_get_temp_dir(), 'ib-settings-config-');
        unlink($path);
        $repository->set('APP_CONFIG_CACHE', $path);
        app()->addAbsoluteCachePathPrefix('C:');
        try {
            $this->artisan('settings:sync')->assertSuccessful();
            $this->artisan('config:cache')->assertSuccessful();
            $cached = require $path;
            Container::setInstance($this->app);
            Facade::setFacadeApplication($this->app);
            Facade::clearResolvedInstances();
            self::assertArrayNotHasKey('settings_values', $cached);
            $this->patchJson('/api/ib/v1/admin/settings/rewards.negative_pnl.enabled', ['value' => false, 'lock_version' => 1, 'reason' => 'Change after caching'])->assertOk();
            self::assertFalse(app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.enabled'])->get('rewards.negative_pnl.enabled'));
        } finally {
            $previous === null ? $repository->clear('APP_CONFIG_CACHE') : $repository->set('APP_CONFIG_CACHE', $previous);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public static function invalidValues(): iterable
    {
        yield 'boolean string' => ['boolean', 'false'];
        yield 'integer string' => ['integer', '10'];
        yield 'float amount' => ['decimal', 0.01];
        yield 'negative amount' => ['decimal', '-0.01'];
        yield 'url credentials' => ['url', 'https://user:password@example.com'];
        yield 'non http' => ['url', 'file:///tmp/x'];
        yield 'header injection' => ['string', "value\r\nInjected: yes"];
        yield 'executable list' => ['string_list', [['class' => 'Example']]];
    }

    #[DataProvider('invalidValues')]
    public function test_validator_rejects_unsafe_or_wrong_types(string $type, mixed $value): void
    {
        $definition = new SettingDefinitionData('rewards.test', 'Rewards', 'General', null, 0, 'Test', 'Test', $type);
        $this->expectException(SettingException::class);
        app(SettingValueValidator::class)->validate($definition, $value);
    }
}
