<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlCashFlowEvidenceData;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlReferralData;
use App\Features\Rewards\Contracts\Data\V1\RecordNegativePnlClosureData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;
use App\Features\Rewards\Contracts\Ports\Input\RecordNegativePnlClosurePort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlReferralsPort;
use App\Features\Rewards\Contracts\Strategies\NegativePnlRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\NegativePnlRewardCalculationData;
use App\Features\Rewards\Exceptions\HistoricalPnlCoverageUnavailableException;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use App\Features\Rewards\Factories\NegativePnlProcessingRepositoryFactory;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use App\Features\Rewards\Services\Strategies\NegativePnlShareCalculationStrategy;
use App\Features\Rewards\UseCases\ProcessNegativePnlRewardsUseCase;
use App\Features\Rewards\UseCases\SettlePendingRewardsUseCase;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class NegativePnlProcessingTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private ResolveNegativePnlPeriodsPort $broker;

    private ResolveNegativePnlReferralsPort $network;

    private string $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T00:00:00Z'));
        $this->seedAuthorizedAdmin();
        $this->customer = $this->seedAuthorizedCustomer();
        Http::preventStrayRequests();
        config(['rewards.negative_pnl.enabled' => true, 'rewards.negative_pnl.retry_delay_seconds' => 0]);
        $this->network = new class implements ResolveNegativePnlReferralsPort
        {
            public int $calls = 0;

            public array $items = [];

            public function resolve(string $beneficiaryId, int $maxDistributionLevel): array
            {
                $this->calls++;

                return $this->items;
            }
        };
        $this->network->items = [new NegativePnlReferralData((string) Str::uuid7(), 0)];
        $this->app->instance(ResolveNegativePnlReferralsPort::class, $this->network);
        $this->broker = new class implements ResolveNegativePnlPeriodsPort
        {
            public array $queries = [];

            public array $accounts = ['account-one'];

            public array $accountsByUser = [];

            public array $currenciesByAccount = [];

            public string $dailyLoss = '100';

            public string $currency = 'USD';

            public ?int $failAt = null;

            public ?\Throwable $failure = null;

            public function resolve(ResolveNegativePnlPeriodsQueryData $query): ResolveNegativePnlPeriodsResultData
            {
                $this->queries[] = $query;
                if ($this->failure !== null) {
                    throw $this->failure;
                }
                if ($this->failAt === count($this->queries)) {
                    throw NegativePnlPeriodsUnavailableException::create();
                }
                $rows = [];
                foreach ($this->accountsByUser[$query->external_user_id] ?? $this->accounts as $account) {
                    $baseline = collect($query->baselines)->firstWhere('account_id', $account);
                    $days = (int) CarbonImmutable::parse('2026-10-01T00:00:00Z')->diffInDays(CarbonImmutable::parse($query->occurred_until));
                    $seconds = (int) CarbonImmutable::parse('2026-10-01T00:00:00Z')->diffInSeconds(CarbonImmutable::parse($query->occurred_until));
                    $balance = bcsub('10000', bcmul(bcdiv((string) $seconds, '86400', 12), $this->dailyLoss, 2), 2);
                    $rows[] = new NegativePnlPeriodData($baseline === null ? 'baseline' : 'resolved', $account, $query->external_user_id, 'external-group', $this->currenciesByAccount[$account] ?? $this->currency, 2, $baseline?->balance_after, $balance, $baseline?->occurred_until, $query->occurred_until, '0', '0', '0', $baseline === null ? null : bcsub($balance, $baseline->balance_after, 2), new NegativePnlCashFlowEvidenceData(0, [], 0, [], 0, [], 0, []), 'read-'.$account.'-'.$days, $query->occurred_until);
                }

                return new ResolveNegativePnlPeriodsResultData($rows);
            }
        };
        $this->app->instance(ResolveNegativePnlPeriodsPort::class, $this->broker);
    }

    public function test_first_baseline_then_each_delayed_period_and_no_duplicate_or_settlement(): void
    {
        config(['rewards.negative_pnl.settlement_enabled' => false]);
        $f = $this->activeFixture();
        self::assertSame(1, $this->process()['periods']);
        self::assertSame(0, DB::table('rewards')->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-04T10:00:00Z'));
        $metrics = $this->process();
        self::assertSame(3, $metrics['periods']);
        self::assertSame(3, $metrics['rewards']);
        self::assertSame(0, $metrics['errors']);
        self::assertSame([1000, 1000, 1000], DB::table('rewards')->orderBy('created_at')->pluck('amount_minor')->map(fn ($x) => (int) $x)->all());
        self::assertSame('2026-10-04T00:00:00.000000Z', $this->broker->queries[3]->occurred_until);
        self::assertSame(0, $this->process()['rewards']);
        self::assertSame(3, DB::table('rewards')->where('status', 'pending')->count());
        self::assertNull(app(RewardRepositoryFactory::class)->make()->claimNextSettlement(now('UTC')->toImmutable(), now('UTC')->subMinute()->toImmutable(), now('UTC')->addMinute()->toImmutable(), []));
        self::assertSame($f['subscription']['id'], json_decode(DB::table('rewards')->first()->summary_snapshot, true)['inputs']['subscription']['subscription_id']);
        Http::assertNothingSent();
    }

    public function test_local_configuration_baseline_period_reward_and_finance_settlement_flow(): void
    {
        $fixture = $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
        $reward = DB::table('rewards')->first();
        self::assertSame(0, $reward->network_level);
        self::assertSame('pending', $reward->status);
        Http::fake(fn ($request) => Http::response(['data' => [
            'status' => 'created', 'currency_code' => 'USD', 'minor_units' => 2, 'system_wallet_slug' => 'usd-main',
            'event' => ['id' => 991, 'status' => 'posted', 'idempotency_key' => $request['idempotency_key'],
                'commission_type' => $request['commission_type'], 'ib_user_id' => $request['ib_user_id'],
                'amount_minor' => $request['amount_minor'], 'reference_type' => 'reward',
                'reference_id' => $request['reference_id'], 'network_level' => $request['network_level']],
        ]]));
        self::assertSame(['settled' => 1, 'failed' => 0, 'skipped' => 0], app(SettlePendingRewardsUseCase::class)->execute(10));
        self::assertSame(0, $this->process()['rewards']);
        self::assertSame(0, app(SettlePendingRewardsUseCase::class)->execute(10)['settled']);
        $settled = DB::table('rewards')->first();
        self::assertSame('settled', $settled->status);
        self::assertSame(0, $settled->network_level);
        self::assertSame('991', $settled->settlement_reference_id);
        self::assertSame($fixture['subscription']['id'], json_decode($settled->summary_snapshot, true)['inputs']['subscription']['subscription_id']);
        self::assertSame(1, json_decode($settled->settlement_request_snapshot, true)['network_level']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['commission_type'] === 'pnl' && $request['network_level'] === 1 && $request['amount_minor'] === 1000);
    }

    public function test_context_final_rates_and_frozen_inputs_survive_partial_reward_creation(): void
    {
        $f = $this->activeFixture();
        $this->broker->accounts[] = 'account-two';
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
        $this->gatewayJson('PATCH', "/api/ib/v1/admin/subscriptions/{$f['subscription']['id']}/reward-rates", ['personal_rate' => '0.5', 'is_master' => true, 'master_rate' => '2', 'lock_version' => $f['subscription']['lock_version']])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $strategy = new class implements NegativePnlRewardCalculationStrategyInterface
        {
            public int $calls = 0;

            public function calculate(NegativePnlRewardCalculationData $input): ?PositiveMoney
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('failure after first reward');
                }

                return (new NegativePnlShareCalculationStrategy)->calculate($input);
            }
        };
        $this->app->instance(NegativePnlShareCalculationStrategy::class, $strategy);
        self::assertSame(1, $this->process()['errors']);
        self::assertSame(1, DB::table('rewards')->count());
        self::assertSame('ready', DB::table('negative_pnl_periods')->orderByDesc('occurred_until')->first()->status);
        $queries = count($this->broker->queries);
        $networkCalls = $this->network->calls;
        $this->network->items = [];
        config(['rewards.minimum_amount_major' => '999999']);
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame($queries, count($this->broker->queries));
        self::assertSame($networkCalls, $this->network->calls);
        self::assertSame(2, DB::table('rewards')->count());
        $snapshot = json_decode(DB::table('rewards')->first()->summary_snapshot, true);
        self::assertSame('0.50000000', $snapshot['inputs']['subscription']['personal_rate']);
        self::assertSame('2.00000000', $snapshot['inputs']['subscription']['master_rate']);
        self::assertSame('0.01', $snapshot['inputs']['minimum_amount_major']);
    }

    public function test_provider_failure_preserves_baseline_and_other_contexts_continue(): void
    {
        $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $this->broker->failAt = 2;
        self::assertSame(1, $this->process()['errors']);
        self::assertSame('2026-10-01', CarbonImmutable::parse(DB::table('negative_pnl_baselines')->first()->occurred_until)->toDateString());
        self::assertSame(0, DB::table('rewards')->count());
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(1, DB::table('rewards')->count());
    }

    public function test_pause_then_resume_resets_baseline_without_paying_paused_interval(): void
    {
        $f = $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        DB::table('modules')->where('id', $f['module_id'])->update(['processing_status' => 'paused']);
        self::assertSame(0, $this->process()['periods']);
        self::assertCount(1, $this->broker->queries);
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        DB::table('modules')->where('id', $f['module_id'])->update(['processing_status' => 'running']);
        self::assertSame(1, $this->process()['periods']);
        self::assertSame(0, DB::table('rewards')->count());
        self::assertSame('2026-10-03T12:00:00.000000Z', $this->broker->queries[1]->occurred_until);
        $this->travelTo(CarbonImmutable::parse('2026-10-04T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
    }

    public function test_currency_change_starts_a_new_account_baseline(): void
    {
        $this->activeFixture();
        $this->process();
        $this->broker->currency = 'EUR';
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        self::assertSame(0, $this->process()['rewards']);
        self::assertSame('EUR', DB::table('negative_pnl_baselines')->first()->currency_code);
        $this->travelTo(CarbonImmutable::parse('2026-10-03T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame('EUR', DB::table('rewards')->first()->currency_code);
    }

    public function test_cadence_change_establishes_new_baseline_and_recovers_weekly_boundary(): void
    {
        $f = $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T12:00:00Z'));
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => 'weekly'])->assertOk();
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(2, DB::table('negative_pnl_jobs')->count());
        $this->travelTo(CarbonImmutable::parse('2026-10-05T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(2, DB::table('rewards')->count());
    }

    public function test_lease_expiry_and_stale_worker_cannot_confirm_inputs(): void
    {
        $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $repository = app(NegativePnlProcessingRepositoryFactory::class)->make();
        $first = $repository->claim(now('UTC')->toISOString(), 1, []);
        self::assertNotNull($first);
        self::assertNull($repository->claim(now('UTC')->toISOString(), 60, []));
        $this->travel(2)->seconds();
        $second = $repository->claim(now('UTC')->toISOString(), 60, []);
        self::assertNotSame($first->lease_token, $second->lease_token);
        $this->expectException(\RuntimeException::class);
        $repository->reset($first, now('UTC')->toISOString());
    }

    public function test_disabled_runner_makes_no_discovery_or_remote_calls(): void
    {
        $this->activeFixture();
        config(['rewards.negative_pnl.enabled' => false]);
        self::assertSame(['contexts' => 0, 'periods' => 0, 'closures' => 0, 'pending_closures' => 0, 'rewards' => 0, 'errors' => 0], $this->process());
        self::assertSame(0, DB::table('negative_pnl_jobs')->count());
        self::assertCount(0, $this->broker->queries);
        $this->artisan('rewards:process-negative-pnl', ['--limit' => 2])->assertExitCode(0);
    }

    private function process(): array
    {
        return app(ProcessNegativePnlRewardsUseCase::class)->execute(20, 100);
    }

    #[DataProvider('otherCadences')]
    public function test_ordered_utc_boundaries_for_each_cadence(string $cadence, string $now, array $cuts): void
    {
        $f = $this->activeFixture();
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => $cadence])->assertOk();
        $this->process();
        $this->travelTo(CarbonImmutable::parse($now));
        self::assertSame(count($cuts), $this->process()['rewards']);
        self::assertSame(array_map(static fn (string $at): string => CarbonImmutable::parse($at)->toISOString(), $cuts), array_map(static fn ($query): string => $query->occurred_until, array_slice($this->broker->queries, 1)));
    }

    public static function otherCadences(): array
    {
        return [
            ['weekly', '2026-10-12T10:00:00Z', ['2026-10-05T00:00:00Z', '2026-10-12T00:00:00Z']],
            ['monthly', '2026-12-02T10:00:00Z', ['2026-11-01T00:00:00Z', '2026-12-01T00:00:00Z']],
            ['yearly', '2028-01-02T10:00:00Z', ['2027-01-01T00:00:00Z', '2028-01-01T00:00:00Z']],
        ];
    }

    public function test_receipts_survive_partial_evidence_failure_for_independent_referrals_and_currencies(): void
    {
        $this->activeFixture();
        $first = $this->network->items[0]->external_user_id;
        $second = (string) Str::uuid7();
        $this->network->items[] = new NegativePnlReferralData($second, 1);
        $this->broker->accountsByUser = [$first => ['usd-account'], $second => ['eur-account']];
        $this->broker->currenciesByAccount = ['eur-account' => 'EUR'];
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $this->broker->failAt = 4;
        self::assertSame(1, $this->process()['errors']);
        self::assertSame(0, DB::table('rewards')->count());
        $calls = $this->network->calls;
        self::assertSame(2, $this->process()['rewards']);
        self::assertCount(5, $this->broker->queries);
        self::assertSame($calls, $this->network->calls);
        self::assertSame(['EUR', 'USD'], DB::table('rewards')->orderBy('currency_code')->pluck('currency_code')->all());
        self::assertSame([500, 1000], DB::table('rewards')->orderBy('amount_minor')->pluck('amount_minor')->map(fn ($x) => (int) $x)->all());
    }

    #[DataProvider('noRewardCases')]
    public function test_auditable_results_without_reward(string $loss, string $minimum, string $reason): void
    {
        $this->activeFixture();
        $this->broker->dailyLoss = $loss;
        config(['rewards.minimum_amount_major' => $minimum]);
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        self::assertSame(0, $this->process()['rewards']);
        self::assertSame(0, DB::table('rewards')->count());
        $outcomes = json_decode(DB::table('negative_pnl_periods')->orderByDesc('occurred_until')->first()->outcomes, true);
        self::assertSame($reason, $outcomes[$this->network->items[0]->external_user_id]['account-one']);
    }

    public static function noRewardCases(): array
    {
        return [['0', '0.01', 'non_negative_pnl'], ['-100', '0.01', 'non_negative_pnl'], ['100', '11', 'below_minimum_or_rounded_zero']];
    }

    #[DataProvider('invalidEvidenceCases')]
    public function test_invalid_contract_or_absent_coverage_never_advances_baseline(string $exceptionType, string $code): void
    {
        $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $this->broker->failure = $exceptionType::create();
        self::assertSame(1, $this->process()['errors']);
        self::assertSame($code, DB::table('negative_pnl_jobs')->first()->error_code);
        self::assertSame('2026-10-01', CarbonImmutable::parse(DB::table('negative_pnl_baselines')->first()->occurred_until)->toDateString());
        self::assertSame(0, DB::table('rewards')->count());
        $this->broker->failure = null;
        self::assertSame(1, $this->process()['rewards']);
    }

    public static function invalidEvidenceCases(): array
    {
        return [[InvalidNegativePnlPeriodsResponseException::class, 'evidence_invalid'], [HistoricalPnlCoverageUnavailableException::class, 'historical_coverage_unavailable']];
    }

    public function test_cut_snapshot_and_referral_receipt_rollback_together_before_baseline_advance(): void
    {
        $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $actual = app(RewardRepositoryFactory::class)->make();
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('findNegativePnlCut')->andReturnUsing(fn (string $identity) => $actual->findNegativePnlCut($identity));
        $repository->shouldReceive('freezeNegativePnlCut')->once()->andReturnUsing(function ($snapshot) use ($actual): void {
            $actual->freezeNegativePnlCut($snapshot);
            throw new \RuntimeException('failure after local snapshot write');
        });
        $this->app->instance('rewards.repositories.postgresql', $repository);
        self::assertSame(1, $this->process()['errors']);
        self::assertSame(1, DB::table('negative_pnl_cut_snapshots')->count());
        self::assertSame([], json_decode(DB::table('negative_pnl_periods')->orderByDesc('occurred_until')->first()->receipts, true));
        self::assertSame('2026-10-01', CarbonImmutable::parse(DB::table('negative_pnl_baselines')->first()->occurred_until)->toDateString());
        $this->app->instance('rewards.repositories.postgresql', $actual);
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(2, DB::table('negative_pnl_cut_snapshots')->count());
    }

    public function test_cadence_round_trip_between_runs_does_not_pay_the_skipped_interval(): void
    {
        $f = $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T06:00:00Z'));
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => 'weekly'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(500, (int) DB::table('rewards')->first()->amount_minor);
        $snapshot = json_decode(DB::table('rewards')->first()->summary_snapshot, true);
        self::assertSame('2026-10-01T12:00:00.000000Z', $snapshot['cut']['occurred_from']);
    }

    public function test_program_change_in_same_cadence_uses_final_program_for_complete_period(): void
    {
        $f = $this->activeFixture();
        $this->process();
        $this->travelTo(CarbonImmutable::parse('2026-10-01T12:00:00Z'));
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$f['plan_id']}/programs", ['code' => 'advanced', 'name' => 'Advanced', 'entry_threshold' => 100, 'module_ids' => [$f['module_id']]])->assertCreated()->json('data.id');
        $this->assign($f['plan_id'], $f['rule_id'], $program, $f['module_id'], $f['version_id']);
        $this->gatewayJson('PUT', "/api/ib/v1/admin/plans/{$f['plan_id']}/programs/{$program}/negative-pnl-configuration", $f['payload'])->assertOk();
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$f['subscription']['id']}/placement/change", ['program_id' => $program, 'lock_version' => $f['subscription']['lock_version']])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame($program, DB::table('rewards')->first()->program_id);
        self::assertSame(1000, (int) DB::table('rewards')->first()->amount_minor);
        self::assertSame(1, DB::table('negative_pnl_jobs')->count());
    }

    public function test_discovery_cursor_and_failed_context_do_not_starve_second_subscription(): void
    {
        $f = $this->activeFixture();
        $second = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $f['plan_id']], $second)->assertCreated();
        self::assertSame(2, $this->process()['periods']);
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $this->broker->failAt = 3;
        self::assertSame(1, app(ProcessNegativePnlRewardsUseCase::class)->execute(1, 1)['errors']);
        self::assertSame(1, app(ProcessNegativePnlRewardsUseCase::class)->execute(1, 1)['rewards']);
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(2, DB::table('rewards')->count());
        self::assertSame(2, DB::table('negative_pnl_jobs')->count());
    }

    public function test_change_plan_recovers_old_period_and_final_segment_and_seeds_incoming_at_same_boundary(): void
    {
        $source = $this->activeFixture();
        $this->process();
        $destination = $this->configuredPlan('destination');
        $this->travelTo(CarbonImmutable::parse('2026-10-02T12:00:00.123456Z'));
        $incoming = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$source['subscription']['id']}/change-plan", ['plan_id' => $destination['plan_id'], 'lock_version' => $source['subscription']['lock_version']])->assertOk()->json('data');
        $closure = DB::table('negative_pnl_pending_closures')->first();
        self::assertSame($source['subscription']['id'], $closure->subscription_id);
        self::assertSame($incoming['id'], $closure->incoming_subscription_id);
        self::assertTrue(CarbonImmutable::parse($closure->closed_at)->equalTo(CarbonImmutable::parse(DB::table('subscriptions')->where('id', $closure->subscription_id)->value('closed_at'))));
        DB::table('negative_pnl_pending_closures')->delete();
        $this->travelTo(CarbonImmutable::parse('2026-10-03T00:00:00Z'));
        $metrics = $this->process();
        self::assertSame(0, $metrics['errors']);
        self::assertSame(1, $metrics['closures']);
        self::assertSame(3, $metrics['rewards']);
        self::assertSame([500, 1000], DB::table('rewards')->where('plan_id', $source['plan_id'])->orderBy('amount_minor')->pluck('amount_minor')->map(fn ($x) => (int) $x)->all());
        self::assertSame(500, (int) DB::table('rewards')->where('plan_id', $destination['plan_id'])->value('amount_minor'));
        self::assertNull(DB::table('negative_pnl_pending_closures')->first()->completed_at);
        $incomingJob = DB::table('negative_pnl_jobs')->where('subscription_id', $incoming['id'])->first();
        $initial = DB::table('negative_pnl_periods')->where('job_id', $incomingJob->id)->orderBy('occurred_until')->first();
        self::assertTrue(CarbonImmutable::parse($initial->occurred_until)->equalTo(CarbonImmutable::parse($closure->closed_at)));
        self::assertSame(0, $this->process()['rewards']);
        self::assertNotNull(DB::table('negative_pnl_pending_closures')->first()->completed_at);
    }

    public function test_change_plan_exactly_at_cadence_cut_creates_no_empty_or_duplicate_segment(): void
    {
        $source = $this->activeFixture();
        $this->process();
        $destination = $this->configuredPlan('destination');
        $this->travelTo(CarbonImmutable::parse('2026-10-02T00:00:00Z'));
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$source['subscription']['id']}/change-plan", ['plan_id' => $destination['plan_id'], 'lock_version' => $source['subscription']['lock_version']])->assertOk();
        self::assertSame(1, $this->process()['rewards']);
        self::assertSame(1, DB::table('rewards')->count());
        $oldJob = DB::table('negative_pnl_jobs')->where('subscription_id', $source['subscription']['id'])->first();
        self::assertSame(2, DB::table('negative_pnl_periods')->where('job_id', $oldJob->id)->count());
        self::assertNotNull($oldJob->finished_at);
    }

    public function test_change_plan_rollback_removes_closure_and_both_subscription_changes(): void
    {
        $source = $this->activeFixture();
        $destination = $this->configuredPlan('destination');
        $actual = app(RecordNegativePnlClosurePort::class);
        $this->app->instance(RecordNegativePnlClosurePort::class, new class($actual) implements RecordNegativePnlClosurePort
        {
            public function __construct(private readonly RecordNegativePnlClosurePort $actual) {}

            public function execute(RecordNegativePnlClosureData $data): void
            {
                $this->actual->execute($data);
                throw new \RuntimeException('rollback');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$source['subscription']['id']}/change-plan", ['plan_id' => $destination['plan_id'], 'lock_version' => $source['subscription']['lock_version']]);
            self::fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }
        self::assertSame(0, DB::table('negative_pnl_pending_closures')->count());
        self::assertSame(1, DB::table('subscriptions')->count());
        self::assertSame('active', DB::table('subscriptions')->first()->status);
        self::assertSame(1, DB::table('subscription_changes')->count());
    }

    private function configuredPlan(string $code): array
    {
        $f = $this->fixture($code);
        $f['payload']['cadence'] = 'daily';
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk();
        $plan = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$f['plan_id']}")->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$f['plan_id']}/activate", ['lock_version' => $plan['lock_version'], 'reason' => 'Test activation'])->assertOk();

        return $f;
    }

    private function activeFixture(): array
    {
        $f = $this->fixture();
        $f['payload']['cadence'] = 'daily';
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk();
        $plan = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$f['plan_id']}")->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$f['plan_id']}/activate", ['lock_version' => $plan['lock_version'], 'reason' => 'Test activation'])->assertOk();
        $f['subscription'] = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $f['plan_id']], $this->customer)->assertCreated()->json('data');

        return $f;
    }

    /** @return array<string,mixed> */
    private function fixture(string $code = 'pnl'): array
    {
        $module = (string) ModuleRecord::query()->where('code', 'broker')->value('id');
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => $code, 'name' => 'PnL '.$code, 'module_ids' => [$module], 'progression_period' => 'monthly', 'requires_approval' => false])->assertCreated()->json('data.id');
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/programs", ['code' => 'entry', 'name' => 'Entry', 'entry_threshold' => 0, 'module_ids' => [$module]])->assertCreated()->json('data.id');
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/payment-templates', ['name' => 'PnL rates '.$code])->assertCreated()->json('data.id');
        $v = $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, 'rate' => '0.1'], ['distribution_level' => 1, 'rate' => '0.05']]])->assertCreated()->json('data.versions.0');
        $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions/{$v['id']}/publish", ['lock_version' => $v['lock_version']])->assertOk();
        $binding = (string) Str::uuid7();
        DB::table('plan_payment_template_version_bindings')->insert(['id' => $binding, 'plan_id' => $plan, 'template_version_id' => $v['id'], 'created_at' => now('UTC')]);
        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules", ['name' => 'PnL share', 'strategy_type' => 'negative_pnl_share'])->assertCreated()->json('data.id');
        $version = $this->version($plan, $rule, $binding);
        $assignment = $this->assign($plan, $rule, $program, $module, $version);

        return ['plan_id' => $plan, 'program_id' => $program, 'module_id' => $module, 'rule_id' => $rule, 'version_id' => $version, 'binding_id' => $binding, 'assignment_id' => $assignment, 'url' => "/api/ib/v1/admin/plans/{$plan}/programs/{$program}/negative-pnl-configuration", 'payload' => ['cadence' => 'monthly', 'groups' => [['module_id' => $module, 'server_group_id' => 'external-group', 'rule_version_id' => $version]]]];
    }

    private function version(string $plan, string $rule, string $binding): string
    {
        $v = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/versions", ['schema_version' => 1, 'configuration' => ['plan_payment_template_version_binding_id' => $binding]])->assertCreated()->json('data');

        return $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/versions/{$v['id']}/publish", ['lock_version' => $v['lock_version']])->assertOk()->json('data.id');
    }

    private function assign(string $plan, string $rule, string $program, string $module, string $version): string
    {
        return $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/assignments", ['program_id' => $program, 'module_id' => $module, 'rule_version_id' => $version])->assertCreated()->json('data.id');
    }
}
