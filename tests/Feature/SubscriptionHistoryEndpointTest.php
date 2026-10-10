<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\UseCases\RecoverProgressionRunsUseCase;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class SubscriptionHistoryEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private Subscription $subscription;

    private ProgramRecord $second;

    private string $customer;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->customer = $this->seedAuthorizedCustomer();
        $this->start = CarbonImmutable::parse('2026-10-01T00:00:00Z');
        CarbonImmutable::setTestNow($this->start);
        $plan = PlanRecord::factory()->create(['is_active' => true]);
        $first = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'code' => 'basic', 'position' => 1, 'entry_threshold' => 0]);
        $this->second = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'code' => 'advanced', 'position' => 2, 'entry_threshold' => 100]);
        $id = static fn (): string => (string) Str::uuid7();
        $this->subscription = Subscription::requestActive($id(), $this->customer, $plan->id, $first->id, $id(), $id(), SubscriptionActorKind::Iam, $this->customer, null, $id, $this->start->toISOString());
        app(SubscriptionRepositoryFactory::class)->make()->create($this->subscription);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function url(string $history): string
    {
        return '/api/ib/v1/admin/subscriptions/'.$this->subscription->id.'/'.$history;
    }

    public function test_manual_fixed_history_and_terminal_subscription_keep_existing_detail(): void
    {
        $base = '/api/ib/v1/admin/subscriptions/'.$this->subscription->id;
        CarbonImmutable::setTestNow($this->start->addHour());
        $fixed = $this->gatewayJson('POST', $base.'/placement/fix', ['lock_version' => 1, 'program_id' => $this->subscription->currentPlacement()->programId, 'reason' => 'Hold A'])->assertOk()->json('data');
        CarbonImmutable::setTestNow($this->start->addHours(2));
        $moved = $this->gatewayJson('POST', $base.'/placement/change', ['lock_version' => $fixed['lock_version'], 'program_id' => $this->second->id, 'reason' => 'Move B'])->assertOk()->json('data');
        CarbonImmutable::setTestNow($this->start->addHours(3));
        $released = $this->gatewayJson('POST', $base.'/placement/release', ['lock_version' => $moved['lock_version'], 'reason' => 'Release B'])->assertOk()->json('data');
        $changes = $this->gatewayJson('GET', $this->url('changes'))->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.pagination.per_page', 100)->json('data');
        self::assertSame(['request', 'fix_placement', 'change_program', 'release_placement'], array_column($changes, 'action'));
        self::assertSame([false, true, true, false], array_column($changes, 'next_is_fixed'));
        self::assertSame($this->authorizedSub(), $changes[1]['actor_external_user_id']);
        self::assertNull($changes[1]['run_result_id']);
        $detail = $this->gatewayJson('GET', $base)->assertOk()->json('data.changes');
        $withoutReferences = array_map(static function (array $change): array {
            unset($change['run_result_id'], $change['run_id']);

            return $change;
        }, $changes);
        self::assertSame($detail, $withoutReferences);
        $this->gatewayJson('GET', $this->url('changes'), ['page' => 2, 'per_page' => 2])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.action', 'change_program')->assertJsonPath('meta.pagination.total', 4);
        $this->gatewayJson('GET', $this->url('changes'), ['action' => 'fix_placement', 'actor_kind' => 'iam', 'occurred_at_from' => '2026-10-01T01:00:00Z', 'occurred_at_to' => '2026-10-01T02:00:00Z'])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'Hold A')->assertJsonPath('meta.filters.action', 'fix_placement');
        $this->gatewayJson('GET', $this->url('changes'), ['occurred_at_to' => '2026-10-01T01:00:00Z'])->assertOk()->assertJsonCount(1, 'data');
        $this->gatewayJson('GET', $this->url('changes'), ['occurred_at_to' => '2026-10-01T01:00:00.500000Z'])->assertOk()->assertJsonCount(2, 'data');
        $placements = $this->gatewayJson('GET', $this->url('placements'))->assertOk()->assertJsonCount(4, 'data')->json('data');
        self::assertSame([false, true, true, false], array_column($placements, 'is_fixed'));
        self::assertSame($this->subscription->id, $placements[0]['subscription_id']);
        self::assertSame($placements[0]['effective_until'], $placements[1]['effective_from']);
        self::assertNull($placements[3]['effective_until']);
        $this->gatewayJson('GET', $this->url('placements'), ['page' => 2, 'per_page' => 2])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $placements[2]['id']);
        $this->gatewayJson('GET', $this->url('placements'), ['program_id' => $this->second->id, 'is_fixed' => '0', 'overlap_from' => '2026-10-01T03:00:00Z', 'overlap_until' => '2026-10-01T04:00:00Z'])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.filters.is_fixed', false);
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $this->customer)->assertOk()->assertJsonMissingPath('data.changes')->assertJsonMissingPath('data.placements');
        CarbonImmutable::setTestNow($this->start->addHours(4));
        $this->gatewayJson('POST', $base.'/cancel', ['lock_version' => $released['lock_version']])->assertOk();
        $this->gatewayJson('GET', $this->url('changes'))->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.4.next_status', 'ended');
        $this->gatewayJson('GET', $this->url('placements'))->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.3.effective_until', '2026-10-01T04:00:00.000000Z');
    }

    public function test_empty_intervals_remain_visible_but_do_not_overlap(): void
    {
        $base = '/api/ib/v1/admin/subscriptions/'.$this->subscription->id;
        $fixed = $this->gatewayJson('POST', $base.'/placement/fix', ['lock_version' => 1, 'program_id' => $this->subscription->currentPlacement()->programId])->assertOk()->json('data');
        $this->gatewayJson('POST', $base.'/placement/release', ['lock_version' => $fixed['lock_version']])->assertOk();
        $all = $this->gatewayJson('GET', $this->url('placements'))->assertOk()->assertJsonCount(3, 'data')->json('data');
        self::assertSame($all[0]['effective_from'], $all[0]['effective_until']);
        self::assertSame($all[1]['effective_from'], $all[1]['effective_until']);
        $this->gatewayJson('GET', $this->url('placements'), ['overlap_from' => '2026-09-30T00:00:00Z', 'overlap_until' => '2026-10-02T00:00:00Z'])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $all[2]['id']);
        $this->gatewayJson('GET', $this->url('placements'), ['overlap_from' => '2026-09-30T00:00:00Z', 'overlap_until' => '2026-10-01T00:00:00Z'])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_histories_deny_customers_missing_permissions_and_invalid_references(): void
    {
        foreach (['changes', 'placements'] as $history) {
            $this->assertGatewayAuthGuards('GET', $this->url($history));
            $this->customerGatewayJson('GET', $this->url($history), [], $this->customer)->assertForbidden();
            $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/'.$this->subscription->id.'/'.$history, [], $this->customer)->assertNotFound();
            $this->gatewayJson('GET', '/api/ib/v1/admin/subscriptions/'.Str::uuid7().'/'.$history)->assertNotFound()->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_FOUND');
            $this->gatewayJson('GET', '/api/ib/v1/admin/subscriptions/invalid/'.$history)->assertUnprocessable();
        }
        $foreign = ProgramRecord::factory()->create(['plan_id' => PlanRecord::factory()->create()->id]);
        $this->gatewayJson('GET', $this->url('placements'), ['program_id' => $foreign->id])->assertOk()->assertJsonCount(0, 'data');
        $this->gatewayJson('GET', $this->url('placements'), ['program_id' => (string) Str::uuid7()])->assertOk()->assertJsonCount(0, 'data');
    }

    public static function invalidFilters(): array
    {
        return [
            ['changes', ['action' => 'unknown']], ['changes', ['actor_kind' => 'customer']],
            ['changes', ['occurred_at_from' => '2026-02-30T00:00:00Z']], ['changes', ['occurred_at_to' => '2026-10-01T00:00:00+00:00']],
            ['changes', ['occurred_at_from' => '2026-10-01T00:00:00Z', 'occurred_at_to' => '2026-10-01T00:00:00Z']],
            ['placements', ['program_id' => 'invalid']], ['placements', ['is_fixed' => 'yes']],
            ['placements', ['overlap_from' => '2026-10-01T00:00:00Z']], ['placements', ['overlap_until' => '2026-10-02T00:00:00Z']],
            ['placements', ['overlap_from' => '2026-10-02T00:00:00Z', 'overlap_until' => '2026-10-01T00:00:00Z']],
            ['placements', ['overlap_from' => '2026-02-30T00:00:00Z', 'overlap_until' => '2026-10-01T00:00:00Z']],
            ['changes', ['page' => 0]], ['placements', ['per_page' => 101]], ['changes', ['per_page' => 0]],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(string $history, array $filters): void
    {
        $this->gatewayJson('GET', $this->url($history), $filters)->assertUnprocessable();
    }

    public function test_pending_and_rejected_subscription_history_and_subscription_scope(): void
    {
        $id = static fn (): string => (string) Str::uuid7();
        $pending = Subscription::requestPending($id(), $id(), $this->subscription->planId, true, $id(), SubscriptionActorKind::Iam, $this->customer, null, $id, $this->start->toISOString());
        $repository = app(SubscriptionRepositoryFactory::class)->make();
        $repository->create($pending);
        $base = '/api/ib/v1/admin/subscriptions/'.$pending->id;
        $this->gatewayJson('GET', $base.'/changes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.next_status', 'pending');
        $this->gatewayJson('GET', $base.'/placements')->assertOk()->assertJsonCount(0, 'data');
        $this->gatewayJson('POST', $base.'/reject', ['lock_version' => 1, 'reason' => 'Not approved'])->assertOk();
        $this->gatewayJson('GET', $base.'/changes')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.next_status', 'rejected');
        $this->gatewayJson('GET', $this->url('changes'))->assertOk()->assertJsonCount(1, 'data');
        $this->gatewayJson('GET', $base.'/placements')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_progression_up_down_unchanged_and_recovery_are_observable_without_duplicates(): void
    {
        $repository = app(ProgressionRunRepositoryFactory::class)->make();
        $window = ProgressionWindow::of($this->start->subDay(), $this->start);
        $run = $repository->findOrCreateRun($this->subscription->planId, $window, $this->start);
        $result = $repository->findOrCreateResult($run->id, $this->subscription->id, $this->start);
        $repository->markCompleted($result, ExactDecimal::fromString('120'), $this->second->id, $this->start);
        $service = app(ApplyPendingProgressionPlacementsService::class);
        self::assertSame(1, $service->execute($repository, $this->start->addHour(), $run->id, 'close')['applied']);
        $changes = $this->gatewayJson('GET', $this->url('changes'), ['action' => 'progression_placement', 'actor_kind' => 'system'])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.run_result_id', $result->id)->assertJsonPath('data.0.run_id', $run->id)->json('data');
        self::assertNull($changes[0]['actor_external_user_id']);
        $changeId = $changes[0]['id'];
        DB::table('subscription_changes')->where('id', $changeId)->update(['operation_id' => (string) Str::uuid7()]);
        $this->gatewayJson('GET', $this->url('changes'), ['action' => 'progression_placement'])->assertOk()->assertJsonPath('data.0.run_result_id', null)->assertJsonPath('data.0.run_id', null);
        $foreign = Subscription::requestActive((string) Str::uuid7(), (string) Str::uuid7(), $this->subscription->planId, $this->second->id,
            (string) Str::uuid7(), (string) Str::uuid7(), SubscriptionActorKind::Iam, $this->authorizedSub(), null, static fn (): string => (string) Str::uuid7(), $this->start->toISOString());
        app(SubscriptionRepositoryFactory::class)->make()->create($foreign);
        $foreignResult = $repository->findOrCreateResult($run->id, $foreign->id, $this->start);
        $repository->markSkipped($foreignResult, $this->start);
        DB::table('subscription_changes')->where('id', $changeId)->update(['operation_id' => $foreignResult->id]);
        $this->gatewayJson('GET', $this->url('changes'), ['action' => 'progression_placement'])->assertOk()->assertJsonPath('data.0.run_result_id', null);
        DB::table('subscription_changes')->where('id', $changeId)->update(['operation_id' => $result->id]);
        DB::table('subscription_changes')->where('subscription_id', $this->subscription->id)->where('action', 'request')->update(['operation_id' => $result->id]);
        $this->gatewayJson('GET', $this->url('changes'), ['action' => 'request'])->assertOk()->assertJsonPath('data.0.run_result_id', null);

        $recover = app(RecoverProgressionRunsUseCase::class);
        $recover->execute($run->id, $this->start->addHours(2));
        $recover->execute($run->id, $this->start->addHours(3));
        $this->gatewayJson('GET', $this->url('changes'))->assertOk()->assertJsonCount(2, 'data');
        $this->gatewayJson('GET', $this->url('placements'))->assertOk()->assertJsonCount(2, 'data');
        $next = $repository->findOrCreateRun($this->subscription->planId, ProgressionWindow::of($this->start, $this->start->addDay()), $this->start->addDay());
        $down = $repository->findOrCreateResult($next->id, $this->subscription->id, $this->start->addDay());
        $repository->markCompleted($down, ExactDecimal::fromString('0'), $this->subscription->currentPlacement()->programId, $this->start->addDay());
        self::assertSame(1, $service->execute($repository, $this->start->addDay(), $next->id, 'close')['applied']);
        $third = $repository->findOrCreateRun($this->subscription->planId, ProgressionWindow::of($this->start->addDay(), $this->start->addDays(2)), $this->start->addDays(2));
        $same = $repository->findOrCreateResult($third->id, $this->subscription->id, $this->start->addDays(2));
        $repository->markCompleted($same, ExactDecimal::fromString('0'), $this->subscription->currentPlacement()->programId, $this->start->addDays(2));
        self::assertSame(1, $service->execute($repository, $this->start->addDays(2), $third->id, 'close')['unchanged']);
        $this->gatewayJson('GET', $this->url('changes'), ['action' => 'progression_placement'])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.run_result_id', $down->id);
        $this->gatewayJson('GET', $this->url('placements'))->assertOk()->assertJsonCount(3, 'data')->assertJsonMissingPath('data.0.run_result_id');
        self::assertSame(3, DB::table('progression_placement_applications')->whereIn('run_result_id', [$result->id, $down->id, $same->id])->count());
    }
}
