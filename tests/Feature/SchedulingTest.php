<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\Services\AdmitSchedulingRunService;
use App\Features\Scheduling\UseCases\DispatchSchedulingTasksUseCase;
use App\Features\Scheduling\UseCases\SyncSchedulingTasksUseCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class SchedulingTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    private const BASE = '/api/ib/v1/admin/scheduling';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        Http::preventStrayRequests();
        DB::table('rbac_user_permission_snapshots')->where('sub', $this->authorizedSub())->update(['permissions' => json_encode(['ib.scheduling.read', 'ib.scheduling.update', 'ib.scheduling.execute', 'ib.scheduling.audit', 'ib.scheduling.output'], JSON_THROW_ON_ERROR)]);
        app(SyncSchedulingTasksUseCase::class)->execute();
    }

    public function test_catalog_shapes_permissions_and_validation(): void
    {
        $this->gatewayJson('GET', self::BASE.'/tasks')->assertOk()->assertJsonCount(7, 'data')->assertJsonPath('data.0.timezone', 'UTC')->assertJsonPath('meta.pagination.total', 7)->assertJsonMissingPath('data.tasks');
        $url = self::BASE.'/tasks/progression:close-windows';
        $this->gatewayJson('GET', $url)->assertOk()->assertJsonPath('data.version', 1)->assertJsonMissingPath('data.task');
        $this->assertGatewayAuthGuards('GET', $url);
        $this->gatewayJson('PATCH', $url, ['version' => 1, 'reason' => 'Change cadence', 'cron_expression' => '* * * * * *'])->assertUnprocessable();
        $this->gatewayJson('PATCH', $url, ['version' => 1, 'reason' => 'Change cadence', 'command' => 'inspire'])->assertUnprocessable();
        $this->gatewayJson('PATCH', $url, ['version' => 1, 'reason' => 'No fields'])->assertUnprocessable();
        $this->gatewayJson('POST', $url.'/runs', ['reason' => 'Run'])->assertUnprocessable();
        $this->gatewayJson('GET', self::BASE.'/tasks/unknown')->assertNotFound();
    }

    public function test_update_audit_concurrency_and_sync_preserve_configuration(): void
    {
        $url = self::BASE.'/tasks/progression:close-windows';
        $payload = ['version' => 1, 'reason' => 'Maintenance', 'description' => 'Manual close', 'cron_expression' => '0 * * * *', 'automatic_enabled' => false];
        $this->gatewayJson('PATCH', $url, $payload)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.automatic_enabled', false);
        $this->gatewayJson('PATCH', $url, $payload)->assertConflict();
        $this->gatewayJson('PATCH', $url, [...$payload, 'version' => 2])->assertOk()->assertJsonPath('data.version', 2);
        app(SyncSchedulingTasksUseCase::class)->execute();
        $this->gatewayJson('GET', $url)->assertJsonPath('data.description', 'Manual close')->assertJsonPath('data.next_due_at', null);
        $this->gatewayJson('GET', $url.'/audits')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.actor_id', $this->authorizedSub())->assertJsonPath('data.0.before.version', 1)->assertJsonPath('data.0.after.version', 2);
    }

    public function test_manual_disabled_task_idempotency_and_accepted_run_snapshot(): void
    {
        $url = self::BASE.'/tasks/progression:close-windows';
        $this->gatewayJson('PATCH', $url, ['version' => 1, 'reason' => 'Pause', 'automatic_enabled' => false])->assertOk();
        $this->withHeader('Idempotency-Key', 'manual-close-1');
        $run = $this->gatewayJson('POST', $url.'/runs', ['reason' => 'Investigate'])->assertAccepted()->assertJsonPath('data.status', 'queued')->assertJsonMissingPath('data.stdout')->json('data');
        $this->gatewayJson('POST', $url.'/runs', ['reason' => 'Investigate'])->assertAccepted()->assertJsonPath('data.id', $run['id']);
        $this->gatewayJson('POST', $url.'/runs', ['reason' => 'Different'])->assertConflict();
        $this->withHeader('Idempotency-Key', 'manual-close-2');
        $this->gatewayJson('POST', $url.'/runs', ['reason' => 'Investigate'])->assertConflict();
        $this->gatewayJson('PATCH', $url, ['version' => 2, 'reason' => 'New description', 'description' => 'Changed'])->assertOk();
        $this->gatewayJson('GET', self::BASE.'/runs/'.$run['id'])->assertOk()->assertJsonPath('data.configuration.version', 2)->assertJsonPath('data.configuration.automatic_enabled', false);
        $this->gatewayJson('GET', self::BASE.'/runs/'.$run['id'].'/output')->assertOk()->assertJsonPath('data.stdout', '');
        $this->gatewayJson('GET', self::BASE.'/runs?task_code=progression:close-windows&status=queued&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 1);
        self::assertSame(1, DB::table('jobs')->where('queue', 'scheduling')->count());
    }

    public function test_dispatch_deduplicates_minutes_and_records_busy_slots_without_catchup(): void
    {
        $this->travelTo(now('UTC')->setTime(12, 0));
        app(DispatchSchedulingTasksUseCase::class)->execute();
        $jobs = DB::table('jobs')->count();
        app(DispatchSchedulingTasksUseCase::class)->execute();
        self::assertSame($jobs, DB::table('jobs')->count());
        self::assertSame(7, DB::table('scheduling_runs')->count());
        $this->travel(1)->minutes();
        app(DispatchSchedulingTasksUseCase::class)->execute();
        self::assertSame(3, DB::table('scheduling_runs')->where('outcome', 'task_busy')->count());
        $this->travel(59)->minutes();
        app(DispatchSchedulingTasksUseCase::class)->execute();
        self::assertSame(17, DB::table('scheduling_runs')->count());
        self::assertSame($jobs, DB::table('jobs')->count());
    }

    public function test_feature_gate_and_permissions_are_independent(): void
    {
        config()->set('rewards.negative_pnl.enabled', false);
        $this->withHeader('Idempotency-Key', 'pnl-disabled');
        $this->gatewayJson('POST', self::BASE.'/tasks/rewards:process-negative-pnl/runs', ['reason' => 'Check gate'])->assertAccepted()->assertJsonPath('data.status', 'skipped')->assertJsonPath('data.outcome', 'feature_disabled');
        self::assertSame(0, DB::table('jobs')->count());
        $sub = '01993ac2-8750-73fd-b102-ba24fb06d8bd';
        DB::table('rbac_user_permission_snapshots')->insert(['sub' => $sub, 'surface' => 'admin_panel', 'message_key' => 'read-only', 'rev' => 1, 'permissions' => '["ib.scheduling.read"]', 'roles' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $this->gatewayJson('GET', self::BASE.'/tasks', sub: $sub)->assertOk();
        $this->gatewayJson('GET', self::BASE.'/tasks/progression:close-windows/audits', sub: $sub)->assertForbidden();
        $this->gatewayJson('PATCH', self::BASE.'/tasks/progression:close-windows', ['version' => 1, 'reason' => 'Pause', 'automatic_enabled' => false], $sub)->assertForbidden();
        $this->gatewayJson('POST', self::BASE.'/tasks/progression:close-windows/runs', ['reason' => 'Run'], $sub)->assertForbidden();
    }

    public function test_queue_failure_rolls_back_run_admission(): void
    {
        config()->set('queue.connections.scheduling.table', 'missing_scheduling_jobs');
        $this->expectException(QueryException::class);
        try {
            app(AdmitSchedulingRunService::class)->manual(new ManualRunData('progression:close-windows', 'actor', 'test', 'rollback'));
        } finally {
            self::assertSame(0, DB::table('scheduling_runs')->count());
        }
    }
}
