<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\Http\V1\Commands\ResetSettingCommand;
use App\Features\Settings\Http\V1\Commands\UpdateSettingCommand;
use App\Features\Settings\Repositories\InMemory\InMemorySettingRepository;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\Features\Settings\UseCases\ResetSettingUseCase;
use App\Features\Settings\UseCases\SyncSettingsUseCase;
use App\Features\Settings\UseCases\UpdateSettingUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SettingsRepositoryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_repository_contract(): void
    {
        foreach ([app(SettingRepositoryInterface::class), new InMemorySettingRepository] as $repository) {
            $definition = new SettingDefinitionData('rewards.contract', 'Rewards', 'General', null, 0, 'Contract', 'Test', 'integer', minimum: 0);
            $at = now()->toISOString();
            $record = new SettingRecordData($definition, 0, 'stored', 1, $at, $at);
            $repository->transaction(fn () => $repository->save($record, null));
            self::assertSame(0, $repository->records()['rewards.contract']->value);
            self::assertSame([], $repository->records(['missing.key']));
            try {
                $repository->transaction(function () use ($repository): void {
                    $repository->delete('rewards.contract');
                    $repository->audit(new SettingAuditData('rewards.contract', 'test', 'delete', 'rollback', null, null, now()->toISOString()));
                    throw new \RuntimeException('Rollback');
                });
            } catch (\RuntimeException) {
            }
            self::assertCount(1, $repository->records());
            $next = new SettingRecordData($definition, null, 'fallback', 2, $at, $at);
            $repository->transaction(fn () => $repository->save($next, 1));
            self::assertSame('fallback', $repository->records()['rewards.contract']->mode);
            try {
                $repository->transaction(fn () => $repository->save($record, 1));
                self::fail('Expected optimistic conflict.');
            } catch (SettingException) {
            }
            $repository->transaction(fn () => $repository->delete('rewards.contract'));
            self::assertSame([], $repository->records());
        }
        self::assertSame(0, DB::table('setting_audits')->count());
    }

    public function test_postgresql_lock_serializes_writers_across_connections(): void
    {
        config()->set('database.connections.settings_lock_test', config('database.connections.pgsql_testing'));
        $other = DB::connection('settings_lock_test');
        try {
            app(SettingRepositoryInterface::class)->transaction(function () use ($other): void {
                $other->beginTransaction();
                $row = $other->selectOne('SELECT pg_try_advisory_xact_lock(71931007) AS acquired');
                self::assertFalse($row->acquired);
                $other->rollBack();
            });
        } finally {
            DB::purge('settings_lock_test');
        }
    }

    public function test_same_sync_resolution_update_and_reset_flow_runs_on_both_repositories(): void
    {
        $postgresql = app(SettingRepositoryInterface::class);
        foreach ([$postgresql, new InMemorySettingRepository] as $repository) {
            app()->instance(SettingRepositoryInterface::class, $repository);
            $definition = new SettingDefinitionData('rewards.contract', 'Rewards', 'General', null, 0, 'Contract', 'Test', 'integer', minimum: 0);
            app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([$definition]));
            config()->set('rewards.contract', 5);
            self::assertSame(1, app(SyncSettingsUseCase::class)->execute()->created);
            app(UpdateSettingUseCase::class)->execute(new UpdateSettingCommand('rewards.contract', 0, 1, 'Customize', 'test'));
            self::assertSame(0, app(ResolveSettingsPort::class)->execute(['rewards.contract'])->get('rewards.contract'));
            app(ResetSettingUseCase::class)->execute(new ResetSettingCommand('rewards.contract', 2, 'Restore fallback', 'test'));
            config()->set('rewards.contract', 9);
            self::assertSame(1, app(SyncSettingsUseCase::class)->execute()->unchanged);
            self::assertSame(9, app(ResolveSettingsPort::class)->execute(['rewards.contract'])->get('rewards.contract'));
            app()->instance(SettingDefinitionRegistry::class, new SettingDefinitionRegistry([]));
            self::assertSame(1, app(SyncSettingsUseCase::class)->execute()->deleted);
        }
    }
}
