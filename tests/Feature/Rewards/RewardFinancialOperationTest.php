<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\Exceptions\RewardFinancialOperationNotAllowedException;
use App\Features\Rewards\UseCases\ManageRewardFinancialOperationUseCase;
use App\Features\Rewards\UseCases\ReconcileRewardSettlementsUseCase;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RewardFinancialOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_cancellation_during_an_active_settlement_lease(): void
    {
        $rewardId = $this->seedReward('pending');
        DB::table('rewards')->where('id', $rewardId)->update(['settlement_lock_expires_at' => now('UTC')->addMinute()]);
        Http::fake();
        $this->expectException(RewardFinancialOperationNotAllowedException::class);
        app(ManageRewardFinancialOperationUseCase::class)->execute(new ManageRewardFinancialOperationData($rewardId, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'cancellation', 'admin.cancelled'));
    }

    public function test_it_cancels_only_when_finance_has_no_settlement_event(): void
    {
        $rewardId = $this->seedReward('pending');
        Http::fake(['http://finance.test/*' => Http::response(['data' => []])]);

        $result = app(ManageRewardFinancialOperationUseCase::class)->execute(new ManageRewardFinancialOperationData($rewardId, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'cancellation', 'admin.cancelled'));

        self::assertSame('completed', $result->status);
        self::assertSame('cancelled', DB::table('rewards')->where('id', $rewardId)->value('status'));
    }

    public function test_it_reverses_a_settled_reward_with_a_finance_event(): void
    {
        $rewardId = $this->seedReward('settled');
        DB::table('rewards')->where('id', $rewardId)->update(['commission_type' => 'volume', 'network_level' => 3]);
        Http::fake(['http://finance.test/*' => Http::response(['data' => ['status' => 'created', 'currency_code' => 'USD', 'minor_units' => 2, 'system_wallet_slug' => 'usd-main', 'event' => ['id' => 782, 'status' => 'posted', 'idempotency_key' => 'ib-service:reward:'.$rewardId.':reversal', 'commission_type' => 'reversal', 'ib_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'amount_minor' => 2500, 'reference_type' => 'reward', 'reference_id' => $rewardId, 'reverses_commission_event_id' => 781, 'network_level' => 3]]])]);

        $result = app(ManageRewardFinancialOperationUseCase::class)->execute(new ManageRewardFinancialOperationData($rewardId, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'reversal', 'admin.reversal'));

        self::assertSame('completed', $result->status);
        self::assertSame('reversed', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertSame('782', DB::table('reward_financial_operations')->where('reward_id', $rewardId)->value('provider_reference_id'));
        Http::assertSent(fn ($request): bool => $request['network_level'] === 3);
    }

    public function test_reversal_rejects_a_string_original_event_reference(): void
    {
        $rewardId = $this->seedReward('settled');
        $event = ['id' => 782, 'status' => 'posted', 'idempotency_key' => 'ib-service:reward:'.$rewardId.':reversal', 'commission_type' => 'reversal', 'ib_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'amount_minor' => 2500, 'reference_type' => 'reward', 'reference_id' => $rewardId, 'reverses_commission_event_id' => 781, 'network_level' => 1, 'currency_code' => 'USD', 'minor_units' => 2, 'system_wallet_slug' => 'usd-main'];
        $invalid = [...$event, 'reverses_commission_event_id' => '781'];
        Http::fake(['http://finance.test/*' => Http::sequence()->push(['data' => ['status' => 'created', 'currency_code' => 'USD', 'minor_units' => 2, 'system_wallet_slug' => 'usd-main', 'event' => $invalid]])->push(['data' => []])->push(['data' => [$event]])]);
        $result = app(ManageRewardFinancialOperationUseCase::class)->execute(new ManageRewardFinancialOperationData($rewardId, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'reversal', 'admin.reversal'));
        self::assertSame('failed', $result->status);
        self::assertSame('reversal_failed', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertNotNull(DB::table('rewards')->where('id', $rewardId)->value('reconciliation_hold_at'));
        self::assertSame(0, app(ReconcileRewardSettlementsUseCase::class)->execute(1)['confirmed']);
        self::assertSame(1, Http::recorded(fn ($request) => $request->method() === 'POST')->count());
        self::assertSame(1, app(ReconcileRewardSettlementsUseCase::class)->execute(1)['confirmed']);
        self::assertSame('reversed', DB::table('rewards')->where('id', $rewardId)->value('status'));
        self::assertNull(DB::table('rewards')->where('id', $rewardId)->value('reconciliation_hold_at'));
        self::assertSame(1, Http::recorded(fn ($request) => $request->method() === 'POST')->count());
    }

    private function seedReward(string $status): string
    {
        config()->set('finance.base_url', 'http://finance.test');
        $now = now('UTC');
        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::factory()->create(['code' => 'broker']);
        $rule = RuleRecord::query()->create(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'Reward correction '.Str::random(), 'slug' => 'reward-correction-'.Str::lower(Str::random()), 'strategy_type' => RuleStrategyType::CpaFixedAmount->value, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $version = RuleVersionRecord::query()->create(['id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1, 'status' => RuleVersionStatus::Published->value, 'schema_version' => 1, 'configuration' => ['currency_precision' => 2], 'published_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $assignment = (string) Str::uuid7();
        DB::table('rule_assignments')->insert(['id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'program_id' => $program->id, 'module_id' => $module->id, 'scope_type' => 'all', 'starts_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $rewardId = (string) Str::uuid7();
        DB::table('rewards')->insert(['id' => $rewardId, 'beneficiary_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'plan_id' => $plan->id, 'program_id' => $program->id, 'module_id' => $module->id, 'rule_assignment_id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'amount_minor' => 2500, 'currency_code' => 'USD', 'currency_precision' => 2, 'status' => $status, 'summary_snapshot' => '{}', 'settlement_idempotency_key' => 'ib-service:reward:'.$rewardId.':settlement', 'settlement_reference_id' => $status === 'settled' ? '781' : null, 'created_at' => $now, 'updated_at' => $now]);

        return $rewardId;
    }
}
