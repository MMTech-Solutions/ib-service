<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rewards\UseCases\SettlePendingRewardsUseCase;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RewardSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pnl_is_enabled_and_financial_level_is_frozen_across_retries(): void
    {
        config(['finance.base_url' => 'http://finance.test', 'rewards.settlement.retry_delay_seconds' => 0]);
        $id = $this->seedReward('pending');
        DB::table('rewards')->where('id', $id)->update(['commission_type' => 'pnl', 'network_level' => 0]);
        $response = $this->financeResponse($id, 'duplicate');
        $response['data']['event']['commission_type'] = 'pnl';
        Http::fake(['*' => Http::sequence()->push([], 503)->push($response)]);
        self::assertSame(1, app(SettlePendingRewardsUseCase::class)->execute(1)['failed']);
        $snapshot = DB::table('rewards')->where('id', $id)->value('settlement_request_snapshot');
        self::assertSame(1, json_decode($snapshot, true)['network_level']);
        DB::table('rewards')->where('id', $id)->update(['network_level' => 8]);
        self::assertSame(1, app(SettlePendingRewardsUseCase::class)->execute(1)['settled']);
        self::assertSame($snapshot, DB::table('rewards')->where('id', $id)->value('settlement_request_snapshot'));
        Http::assertSent(fn ($request) => $request['network_level'] === 1 && $request['commission_type'] === 'pnl');
    }

    public function test_pnl_settlement_can_be_disabled_independently(): void
    {
        config(['rewards.negative_pnl.settlement_enabled' => false]);
        $id = $this->seedReward('pending');
        DB::table('rewards')->where('id', $id)->update(['commission_type' => 'pnl', 'network_level' => 0]);
        Http::fake();
        self::assertSame(0, app(SettlePendingRewardsUseCase::class)->execute(1)['settled']);
        Http::assertNothingSent();
    }

    public function test_volume_level_is_translated_without_changing_economic_level(): void
    {
        config(['finance.base_url' => 'http://finance.test']);
        $id = $this->seedReward('pending');
        DB::table('rewards')->where('id', $id)->update(['commission_type' => 'volume', 'network_level' => 2]);
        $response = $this->financeResponse($id, 'created');
        $response['data']['event']['network_level'] = 3;
        $response['data']['event']['commission_type'] = 'volume';
        Http::fake(fn () => Http::response($response));
        self::assertSame(1, app(SettlePendingRewardsUseCase::class)->execute(1)['settled']);
        self::assertSame(2, DB::table('rewards')->where('id', $id)->value('network_level'));
    }

    public function test_finance_precision_must_be_an_integer(): void
    {
        config(['finance.base_url' => 'http://finance.test']);
        $id = $this->seedReward('pending');
        $response = $this->financeResponse($id, 'created');
        $response['data']['minor_units'] = '2';
        Http::fake(fn () => Http::response($response));
        self::assertSame(1, app(SettlePendingRewardsUseCase::class)->execute(1)['failed']);
        self::assertSame('finance_contract_invalid', DB::table('rewards')->where('id', $id)->value('last_settlement_error_code'));
        self::assertNotNull(DB::table('rewards')->where('id', $id)->value('reconciliation_hold_at'));
        self::assertSame(0, app(SettlePendingRewardsUseCase::class)->execute(1)['settled']);
        Http::assertSentCount(1);
    }

    public function test_an_operational_context_failure_releases_its_lease_and_other_rewards_continue(): void
    {
        config(['finance.base_url' => 'http://finance.test']);
        $first = $this->seedReward('pending');
        $second = $this->seedReward('pending');
        DB::table('rewards')->where('id', $first)->update(['created_at' => now('UTC')->subMinute()]);
        $actual = app(ResolveModulesPort::class);
        $calls = 0;
        $mock = \Mockery::mock(ResolveModulesPort::class);
        $mock->shouldReceive('findByIds')->andReturnUsing(function ($ids) use ($actual, &$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('Owner context unavailable');
            }

            return $actual->findByIds($ids);
        });
        $this->app->instance(ResolveModulesPort::class, $mock);
        Http::fake(fn () => Http::response($this->financeResponse($second, 'created')));
        self::assertSame(['settled' => 1, 'failed' => 0, 'skipped' => 1], app(SettlePendingRewardsUseCase::class)->execute(1));
        self::assertNull(DB::table('rewards')->where('id', $first)->value('settlement_lock_token'));
        self::assertSame('pending', DB::table('rewards')->where('id', $first)->value('status'));
        Http::assertSentCount(1);
    }

    public function test_a_held_reward_does_not_block_other_settlements(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        $held = $this->seedReward('pending');
        DB::table('rewards')->where('id', $held)->update(['reconciliation_hold_at' => now('UTC'), 'reconciliation_hold_code' => 'contract_mismatch']);
        $payable = $this->seedReward('pending');
        Http::fake(fn () => Http::response($this->financeResponse($payable, 'created'), 201));

        $result = app(SettlePendingRewardsUseCase::class)->execute(10);

        self::assertSame(1, $result['settled']);
        self::assertSame('pending', DB::table('rewards')->where('id', $held)->value('status'));
        Http::assertSentCount(1);
    }

    public function test_a_paused_plan_is_skipped_without_blocking_another_plan(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        $paused = $this->seedReward('pending');
        DB::table('rewards')->where('id', $paused)->update(['created_at' => now('UTC')->subMinute()]);
        PlanRecord::query()->where('id', DB::table('rewards')->where('id', $paused)->value('plan_id'))->update(['is_active' => false]);
        $payable = $this->seedReward('pending');
        Http::fake(fn () => Http::response($this->financeResponse($payable, 'created'), 201));

        $result = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(['settled' => 1, 'failed' => 0, 'skipped' => 1], $result);
        self::assertNull(DB::table('rewards')->where('id', $paused)->value('settlement_lock_token'));
        Http::assertSentCount(1);
    }

    public function test_finance_level_mismatch_does_not_confirm_settlement(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        $rewardId = $this->seedReward('pending');
        $response = $this->financeResponse($rewardId, 'created');
        $response['data']['event']['network_level'] = 2;
        Http::fake(fn () => Http::response($response, 201));

        $result = app(SettlePendingRewardsUseCase::class)->execute(10);

        self::assertSame(1, $result['failed']);
        self::assertSame('failed', DB::table('rewards')->where('id', $rewardId)->value('status'));
    }

    public function test_it_settles_a_pending_cpa_reward_with_the_finance_commission_contract(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        config()->set('finance.internal_token', 'finance-token');
        config()->set('finance.source_service', 'mmt-ib-service');
        $rewardId = $this->seedReward('pending');
        Http::fake(fn () => Http::response($this->financeResponse($rewardId, 'created'), 201));

        $result = app(SettlePendingRewardsUseCase::class)->execute(10);

        self::assertSame(['settled' => 1, 'failed' => 0, 'skipped' => 0], $result);
        $reward = DB::table('rewards')->where('id', $rewardId)->first();
        self::assertSame('settled', $reward->status);
        self::assertSame('finance', $reward->settlement_provider);
        self::assertSame('781', $reward->settlement_reference_id);
        self::assertSame('ib-service:reward:'.$rewardId.':settlement', $reward->settlement_idempotency_key);
        self::assertSame(1, $reward->settlement_attempt_count);
        self::assertNull($reward->last_settlement_error_code);
        self::assertNotNull($reward->settled_at);
        Http::assertSent(function (Request $request) use ($rewardId): bool {
            return str_contains($request->url(), '/api/finance/v1/ib/commission-events')
                && $request->hasHeader('X-Internal-Token', 'finance-token')
                && $request->hasHeader('X-Internal-Source', 'mmt-ib-service')
                && $request->data()['idempotency_key'] === 'ib-service:reward:'.$rewardId.':settlement'
                && $request->data()['system_wallet_slug'] === 'usd-main'
                && $request->data()['commission_type'] === 'cpa'
                && $request->data()['amount_minor'] === 2500
                && $request->data()['reference_type'] === 'reward'
                && $request->data()['reference_id'] === $rewardId;
        });
    }

    public function test_it_recovers_a_failed_reward_when_finance_returns_the_idempotent_duplicate(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        config()->set('rewards.settlement.retry_delay_seconds', 0);
        $rewardId = $this->seedReward('failed', now('UTC')->subMinute());
        Http::fake(fn () => Http::response($this->financeResponse($rewardId, 'duplicate'), 200));

        $result = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(1, $result['settled']);
        self::assertSame('settled', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertSame('781', DB::table('rewards')->where('id', $rewardId)->value('settlement_reference_id'));
    }

    public function test_it_marks_the_reward_failed_and_retries_it_after_the_configured_delay(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        config()->set('rewards.settlement.retry_delay_seconds', 0);
        $rewardId = $this->seedReward('pending');
        Http::fakeSequence()->pushStatus(503)->push($this->financeResponse($rewardId, 'duplicate'), 200);

        $first = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(1, $first['failed']);
        self::assertSame('failed', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertSame('finance_unavailable', DB::table('rewards')->where('id', $rewardId)->value('last_settlement_error_code'));

        $second = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(1, $second['settled']);
        self::assertSame('settled', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertSame(2, DB::table('rewards')->where('id', $rewardId)->value('settlement_attempt_count'));
    }

    public function test_it_does_not_claim_a_reward_with_an_active_settlement_lease(): void
    {
        $rewardId = $this->seedReward('pending');
        DB::table('rewards')->where('id', $rewardId)->update([
            'settlement_lock_token' => (string) Str::uuid7(),
            'settlement_lock_expires_at' => now('UTC')->addMinute(),
        ]);
        Http::fake();

        $result = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(['settled' => 0, 'failed' => 0, 'skipped' => 0], $result);
        Http::assertNothingSent();
    }

    public function test_it_marks_a_reward_failed_when_finance_returns_an_invalid_contract(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        $rewardId = $this->seedReward('pending');
        Http::fake(fn () => Http::response(['data' => ['status' => 'created']], 201));

        $result = app(SettlePendingRewardsUseCase::class)->execute(1);

        self::assertSame(1, $result['failed']);
        self::assertSame('failed', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertSame('finance_contract_invalid', DB::table('rewards')->where('id', $rewardId)->value('last_settlement_error_code'));
    }

    private function seedReward(string $status, mixed $lastAttempt = null): string
    {
        $now = now('UTC');
        $plan = PlanRecord::factory()->create(['is_active' => true]);
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::query()->where('code', 'broker')->first() ?? ModuleRecord::factory()->create(['code' => 'broker']);
        $rule = RuleRecord::query()->create([
            'id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'CPA Settlement Rule '.Str::random(8),
            'slug' => 'cpa-settlement-'.Str::lower(Str::random(8)), 'description' => null,
            'strategy_type' => RuleStrategyType::CpaFixedAmount->value, 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $version = RuleVersionRecord::query()->create([
            'id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1,
            'status' => RuleVersionStatus::Published->value, 'schema_version' => 1,
            'configuration' => ['currency_precision' => 2], 'published_at' => $now, 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $assignmentId = (string) Str::uuid7();
        DB::table('rule_assignments')->insert([
            'id' => $assignmentId, 'rule_id' => $rule->id, 'rule_version_id' => $version->id,
            'program_id' => $program->id, 'module_id' => $module->id, 'scope_type' => 'all',
            'starts_at' => $now, 'ends_at' => null, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $rewardId = (string) Str::uuid7();
        DB::table('rewards')->insert([
            'id' => $rewardId, 'beneficiary_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'plan_id' => $plan->id, 'program_id' => $program->id, 'module_id' => $module->id,
            'rule_assignment_id' => $assignmentId, 'rule_id' => $rule->id, 'rule_version_id' => $version->id,
            'amount_minor' => 2500, 'currency_code' => 'USD', 'currency_precision' => 2,
            'status' => $status, 'summary_snapshot' => '{}', 'last_settlement_attempt_at' => $lastAttempt,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('cpa_contexts')->insert([
            'id' => (string) Str::uuid7(), 'referred_user_id' => (string) Str::uuid7(),
            'ib_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'plan_id' => $plan->id,
            'program_id' => $program->id, 'module_id' => $module->id, 'rule_assignment_id' => $assignmentId,
            'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'symbols_snapshot' => '[]',
            'requirements_snapshot' => '{}', 'captured_at' => $now, 'reward_id' => $rewardId,
        ]);

        return $rewardId;
    }

    /** @return array<string, mixed> */
    private function financeResponse(string $rewardId, string $status): array
    {
        return [
            'data' => [
                'status' => $status,
                'currency_code' => 'USD',
                'minor_units' => 2,
                'system_wallet_slug' => 'usd-main',
                'event' => [
                    'id' => 781, 'status' => 'posted', 'idempotency_key' => 'ib-service:reward:'.$rewardId.':settlement',
                    'commission_type' => 'cpa', 'ib_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    'amount_minor' => 2500, 'reference_type' => 'reward', 'reference_id' => $rewardId,
                    'network_level' => 1,
                ],
            ],
        ];
    }
}
