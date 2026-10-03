<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\Exceptions\RewardFinancialOperationNotAllowedException;
use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
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

final class RewardFinancialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncertain_cancellation_recovers_original_payment_and_rejects_cancellation(): void
    {
        $id = $this->seedReward('failed');
        DB::table('rewards')->where('id', $id)->update(['settlement_attempt_count' => 1]);
        Http::fake(['*' => Http::sequence()->push(['data' => []])->push($this->posted($id))]);
        $result = app(ManageRewardFinancialOperationUseCase::class)->execute($this->command($id, 'cancellation'));
        self::assertSame('rejected_already_settled', $result->outcome);
        self::assertSame('settled', DB::table('rewards')->where('id', $id)->value('status'));
        self::assertSame('781', DB::table('rewards')->where('id', $id)->value('settlement_reference_id'));
        Http::assertSentCount(2);
    }

    public function test_failed_cancellation_is_recovered_by_reconciler(): void
    {
        $id = $this->seedReward('failed');
        DB::table('rewards')->where('id', $id)->update(['settlement_attempt_count' => 1]);
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['data' => []])->push($this->posted($id))]);
        $result = app(ManageRewardFinancialOperationUseCase::class)->execute($this->command($id, 'cancellation'));
        self::assertSame('failed', $result->status);
        self::assertSame('failed', DB::table('rewards')->where('id', $id)->value('status'));
        $metrics = app(ReconcileRewardSettlementsUseCase::class)->execute(10);
        self::assertSame(1, $metrics['confirmed']);
        self::assertSame('settled', DB::table('rewards')->where('id', $id)->value('status'));
    }

    public function test_expired_worker_cannot_confirm_settlement_or_administrative_operation(): void
    {
        $id = $this->seedReward('pending');
        $repository = app(RewardRepositoryFactory::class)->make();
        [$reward, $operation] = $repository->beginFinancialOperation($id, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'cancellation', 'admin.test', null, null, null);
        DB::table('rewards')->where('id', $id)->update(['settlement_lock_expires_at' => now('UTC')->subSecond()]);
        self::assertFalse($repository->markRewardSettled($id, $reward->settlement_lock_token, 'finance', '781', now('UTC')->toImmutable()));
        $this->expectException(RewardSettlementException::class);
        $repository->completeFinancialOperation($id, $operation->id, 'cancelled', null, null, now('UTC')->toImmutable(), $operation->lock_token);
    }

    public function test_compensation_is_unique_and_rejects_changed_amount(): void
    {
        $id = $this->seedReward('settled');
        DB::table('rewards')->where('id', $id)->update(['status' => 'reversed']);
        Http::fake(function ($request) {
            $response = $this->posted($request['reference_id']);
            $response['data']['event']['amount_minor'] = $request['amount_minor'];

            return Http::response($response);
        });
        $command = new ManageRewardFinancialOperationData($id, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'compensation', 'admin.test', null, 100, 'compensation-test');
        $first = app(ManageRewardFinancialOperationUseCase::class)->execute($command);
        $second = app(ManageRewardFinancialOperationUseCase::class)->execute($command);
        self::assertSame('completed', $first->status);
        self::assertSame($first->compensation_reward_id, $second->compensation_reward_id);
        self::assertSame(1, DB::table('rewards')->where('compensates_reward_id', $id)->count());
        Http::assertSentCount(1);
        $this->expectException(RewardFinancialOperationNotAllowedException::class);
        app(ManageRewardFinancialOperationUseCase::class)->execute(new ManageRewardFinancialOperationData($id, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'compensation', 'admin.test', null, 200, 'compensation-test'));
    }

    public function test_a_valid_reward_lease_cannot_confirm_another_rewards_operation(): void
    {
        $first = $this->seedReward('pending');
        $second = $this->seedReward('pending');
        $repository = app(RewardRepositoryFactory::class)->make();
        [$reward] = $repository->beginFinancialOperation($first, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'cancellation', 'admin.test', null, null, null);
        [, $foreign] = $repository->beginFinancialOperation($second, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'cancellation', 'admin.test', null, null, null);
        try {
            $repository->completeFinancialOperation($first, $foreign->id, 'cancelled', null, null, now('UTC')->toImmutable(), $reward->settlement_lock_token);
            self::fail('Expected the foreign operation to be rejected.');
        } catch (RewardSettlementException $exception) {
            self::assertSame('financial_lease_lost', $exception->error_code);
        }
        self::assertSame('pending', DB::table('rewards')->where('id', $first)->value('status'));
        self::assertSame('processing', DB::table('reward_financial_operations')->where('id', $foreign->id)->value('status'));
    }

    public function test_held_compensation_is_confirmed_only_by_a_matching_get_without_another_payment(): void
    {
        $id = $this->seedReward('settled');
        DB::table('rewards')->where('id', $id)->update(['status' => 'reversed']);
        $reads = 0;
        $childId = null;
        Http::fake(function ($request) use (&$reads, &$childId) {
            if ($request->method() === 'POST') {
                $childId = $request['reference_id'];
                $response = $this->posted($childId);
                $response['data']['event']['amount_minor'] = 999;

                return Http::response($response);
            }
            if (++$reads === 1) {
                return Http::response(['data' => []]);
            }
            $event = $this->posted($childId)['data']['event'];
            $event['amount_minor'] = 100;
            $event += ['minor_units' => 2, 'currency_code' => 'USD', 'system_wallet_slug' => 'usd-main'];

            return Http::response(['data' => [$event]]);
        });
        $command = new ManageRewardFinancialOperationData($id, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'compensation', 'admin.test', null, 100, 'held-compensation');
        self::assertSame('failed', app(ManageRewardFinancialOperationUseCase::class)->execute($command)->status);
        self::assertSame(0, app(ReconcileRewardSettlementsUseCase::class)->execute(1)['confirmed']);
        self::assertSame(1, app(ReconcileRewardSettlementsUseCase::class)->execute(1)['confirmed']);
        self::assertSame('settled', DB::table('rewards')->where('id', $childId)->value('status'));
        self::assertSame('reversed', DB::table('rewards')->where('id', $id)->value('status'));
        self::assertNull(DB::table('rewards')->where('id', $id)->value('reconciliation_hold_at'));
        self::assertSame(1, DB::table('rewards')->where('compensates_reward_id', $id)->count());
        self::assertSame(1, Http::recorded(fn ($request) => $request->method() === 'POST')->count());
    }

    private function command(string $id, string $type): ManageRewardFinancialOperationData
    {
        return new ManageRewardFinancialOperationData($id, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $type, 'admin.test');
    }

    /** @return array<string, mixed> */
    private function posted(string $id): array
    {
        return ['data' => ['status' => 'duplicate', 'currency_code' => 'USD', 'minor_units' => 2, 'system_wallet_slug' => 'usd-main', 'event' => ['id' => 781, 'status' => 'posted', 'idempotency_key' => 'ib-service:reward:'.$id.':settlement', 'commission_type' => 'cpa', 'ib_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'amount_minor' => 2500, 'reference_type' => 'reward', 'reference_id' => $id, 'network_level' => 1]]];
    }

    private function seedReward(string $status): string
    {
        config()->set('finance.base_url', 'http://finance.test');
        $now = now('UTC');
        $plan = PlanRecord::factory()->create(['is_active' => true]);
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::query()->where('code', 'broker')->first() ?? ModuleRecord::factory()->create(['code' => 'broker']);
        $rule = RuleRecord::query()->create(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'Reward correction '.Str::random(), 'slug' => 'reward-correction-'.Str::lower(Str::random()), 'strategy_type' => RuleStrategyType::CpaFixedAmount->value, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $version = RuleVersionRecord::query()->create(['id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1, 'status' => RuleVersionStatus::Published->value, 'schema_version' => 1, 'configuration' => ['currency_precision' => 2], 'published_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $assignment = (string) Str::uuid7();
        DB::table('rule_assignments')->insert(['id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'program_id' => $program->id, 'module_id' => $module->id, 'scope_type' => 'all', 'starts_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $rewardId = (string) Str::uuid7();
        DB::table('rewards')->insert(['id' => $rewardId, 'beneficiary_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'plan_id' => $plan->id, 'program_id' => $program->id, 'module_id' => $module->id, 'rule_assignment_id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'amount_minor' => 2500, 'currency_code' => 'USD', 'currency_precision' => 2, 'status' => $status, 'summary_snapshot' => '{}', 'settlement_idempotency_key' => 'ib-service:reward:'.$rewardId.':settlement', 'settlement_reference_id' => $status === 'settled' ? '781' : null, 'created_at' => $now, 'updated_at' => $now]);

        return $rewardId;
    }
}
