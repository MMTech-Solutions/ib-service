<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesResultData;
use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use App\Features\Progression\Contracts\Data\V1\ReferralUplineBeneficiaryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineResultData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\Services\ProgressionExecutionMutex;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class Lab5ContractTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;

    private string $clockFile;

    private string $context;

    /** @var array<string, mixed> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clockFile = storage_path('framework/testing/lab5-'.Str::uuid().'.json');
        if (! is_dir(dirname($this->clockFile))) {
            mkdir(dirname($this->clockFile), 0700, true);
        }
        $this->context = (string) Str::uuid();
        config()->set('lab.clock_file', $this->clockFile);
    }

    protected function tearDown(): void
    {
        if (getenv('IB_LAB5_CAPTURE') === '1' && $this->fixtures !== []) {
            file_put_contents(storage_path('framework/testing/lab5-contract-fixtures.json'), json_encode($this->fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        app(DomainClock::class)->end();
        foreach ([$this->clockFile, $this->clockFile.'.lock', $this->clockFile.'.accepted'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    private function enableLab(): void
    {
        $this->app['env'] = 'local';
        config()->set(['lab.profile' => true, 'lab.clock_mode' => 'controlled', 'lab.failures_enabled' => true]);
        app(DomainClock::class)->end();
    }

    private function advance(int $sequence, string $time): void
    {
        app(DomainClock::class)->advance($this->context, $sequence, $time);
    }

    /** @return array{code: int, report: array<string, mixed>, error: string} */
    private function cli(string $name, array $arguments = []): array
    {
        $output = new class extends BufferedOutput implements ConsoleOutputInterface
        {
            private OutputInterface $errors;

            public function __construct()
            {
                parent::__construct();
                $this->errors = new BufferedOutput;
            }

            public function getErrorOutput(): OutputInterface
            {
                return $this->errors;
            }

            public function setErrorOutput(OutputInterface $error): void
            {
                $this->errors = $error;
            }

            public function section(): ConsoleSectionOutput
            {
                throw new \LogicException('Sections are not used in JSON mode.');
            }
        };
        $code = Artisan::call($name, [...$arguments, '--json' => true], $output);
        $raw = trim($output->fetch());
        $report = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $this->fixtures[$name.'-'.$report['status'].'-'.count($this->fixtures)] = $report;
        self::assertSame(1, $report['version']);
        self::assertTrue(Str::isUuid($report['execution_id']));

        return ['code' => $code, 'report' => $report, 'error' => $output->getErrorOutput()->fetch()];
    }

    public function test_invalid_arguments_and_fail_closed_clock_are_reported_as_single_json(): void
    {
        $invalid = $this->cli('progression:recover-runs', ['--run' => 'wrong']);
        self::assertSame(2, $invalid['code']);
        self::assertSame('invalid', $invalid['report']['status']);
        self::assertNotSame('', $invalid['error']);
        foreach (['2026-02-30T00:00:00Z', '2026-10-05T25:00:00Z', '2026-10-05T00:00:00+00:00'] as $date) {
            $result = $this->cli('progression:evaluate-activities', ['--plan' => (string) Str::uuid(), '--module' => (string) Str::uuid(), '--from' => $date, '--until' => '2026-10-06T00:00:00Z']);
            self::assertSame(2, $result['code']);
        }
        config()->set('lab.clock_mode', 'controlled');
        self::assertSame('failed', $this->cli('progression:close-windows')['report']['status']);
        $this->enableLab();
        self::assertSame(1, $this->cli('progression:close-windows')['code']);
    }

    public function test_clock_survives_restart_and_rejects_regressions_and_concurrent_advance(): void
    {
        $this->enableLab();
        $this->advance(0, '2026-09-10T10:00:00Z');
        $clock = app(DomainClock::class);
        $clock->begin();
        $second = new DomainClock;
        try {
            $second->advance($this->context, 1, '2026-09-10T11:00:00Z');
            self::fail('Clock advance must be excluded.');
        } catch (\RuntimeException $error) {
            self::assertSame('Lab clock is busy.', $error->getMessage());
        }
        $clock->end();
        $second->begin();
        self::assertSame($this->context, $second->context()['context_id']);
        $second->end();
        foreach ([[0, '2026-09-10T11:00:00Z'], [1, '2026-09-10T09:00:00Z']] as [$sequence, $time]) {
            try {
                $second->advance($this->context, $sequence, $time);
                self::fail('Regression accepted.');
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }
        try {
            $second->advance((string) Str::uuid(), 0, '2026-09-10T10:00:00Z');
            self::fail('Context reset accepted.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
        $this->advance(1, '2026-09-10T11:00:00Z');
        $document = json_decode(file_get_contents($this->clockFile), true, flags: JSON_THROW_ON_ERROR);
        $document['sequence'] = 0;
        $document['now_utc'] = '2026-09-10T10:00:00Z';
        file_put_contents($this->clockFile, json_encode($document));
        try {
            (new DomainClock)->begin();
            self::fail('Persisted regression accepted.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
    }

    public function test_http_cli_partial_recovery_keeps_frozen_points_ladder_and_placement(): void
    {
        $this->seedAuthorizedAdmin();
        $firstUser = $this->seedAuthorizedCustomer();
        $secondUser = $this->seedAuthorizedCustomer((string) Str::uuid());
        $this->enableLab();
        $this->advance(0, '2026-09-10T10:00:00Z');
        $moduleId = (string) ModuleRecord::query()->where('code', 'broker')->value('id');
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'lab5', 'name' => 'Lab5', 'module_ids' => [$moduleId], 'requires_approval' => false, 'progression_period' => 'daily'])->assertCreated()->json('data');
        $planId = $plan['id'];
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/progression-templates', ['name' => 'Lab template'])->assertCreated()->json('data.id');
        $templateVersion = $this->gatewayJson('POST', "/api/ib/v1/admin/progression-templates/$template/versions", ['levels' => [['distribution_level' => 0, 'weight' => '0.1'], ['distribution_level' => 1, 'weight' => '0.1']]])->assertCreated()->json('data.versions.0');
        $published = $this->gatewayJson('POST', "/api/ib/v1/admin/progression-templates/$template/versions/{$templateVersion['id']}/publish", ['lock_version' => $templateVersion['lock_version']])->assertOk();
        $binding = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/progression-template-version-bindings", ['template_version_id' => $templateVersion['id']])->assertCreated()->json('data');
        self::assertSame('2026-09-10T10:00:00.000000Z', $binding['created_at']);
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/activate", ['reason' => 'Lab', 'lock_version' => $plan['lock_version']])->assertOk();
        $base = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/programs", ['code' => 'basic', 'name' => 'Basic', 'entry_threshold' => 0, 'module_ids' => [$moduleId]])->assertCreated()->json('data');
        $gold = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/programs", ['code' => 'gold', 'name' => 'Gold', 'entry_threshold' => 100, 'module_ids' => [$moduleId]])->assertCreated()->json('data');
        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/rules", ['name' => 'Points', 'strategy_type' => 'points_per_quantity_unit'])->assertCreated()->json('data');
        $draft = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/rules/{$rule['id']}/versions", ['schema_version' => 1, 'configuration' => ['unit' => 'usd', 'points_per_unit' => '0.1']])->assertCreated()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/rules/{$rule['id']}/versions/{$draft['id']}/publish", ['lock_version' => $draft['lock_version']])->assertOk();
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/$planId/rules/{$rule['id']}/assignments", ['program_id' => $base['id'], 'module_id' => $moduleId, 'rule_version_id' => $draft['id']])->assertCreated();
        $first = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $planId], $firstUser)->assertCreated()->json('data');
        $second = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $planId], $secondUser)->assertCreated()->json('data');
        self::assertSame('2026-09-10 10:00:00+00', DB::table('subscriptions')->where('id', $first['id'])->value('activated_at'));
        $this->app->instance(ResolveReferralUplinePort::class, new class($firstUser, $secondUser) implements ResolveReferralUplinePort
        {
            public function __construct(private readonly string $first, private readonly string $second) {}

            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                return ResolveReferralUplineResultData::resolved([new ReferralUplineBeneficiaryData($this->first, 0), new ReferralUplineBeneficiaryData($this->second, 1)], app(DomainClock::class)->now()->toISOString());
            }
        });
        $source = (string) Str::uuid();
        $this->app->instance(FetchProgressionActivitiesPort::class, new class($moduleId, $source) implements FetchProgressionActivitiesPort
        {
            public function __construct(private readonly string $module, private readonly string $source) {}

            public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
            {
                return new FetchProgressionActivitiesResultData($this->module, 'running', true, [new NormalizedActivityData($this->module, 'lab5-deposit', $this->source, 'confirmed_deposit', 'usd', '1200', '2026-09-10T12:00:00Z')]);
            }
        });
        $this->advance(1, '2026-09-10T13:00:00Z');
        $query = ['--plan' => $planId, '--module' => $moduleId, '--from' => '2026-09-10T00:00:00Z', '--until' => '2026-09-11T00:00:00Z', '--limit' => '1'];
        $evaluated = $this->cli('progression:evaluate-activities', $query);
        self::assertSame(0, $evaluated['code']);
        self::assertSame(2, $evaluated['report']['counts']['accepted']);
        self::assertSame($evaluated['report']['ids'], $this->cli('progression:evaluate-activities', $query)['report']['ids']);
        $provider = $this->app->make(FetchProgressionActivitiesPort::class);
        $this->app->instance(FetchProgressionActivitiesPort::class, new class($provider) implements FetchProgressionActivitiesPort
        {
            public function __construct(private readonly FetchProgressionActivitiesPort $provider) {}

            public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
            {
                if ($query->cursor !== null) {
                    throw new \RuntimeException('Private upstream error must not escape.');
                }
                $page = $this->provider->fetch($query);

                return new FetchProgressionActivitiesResultData($page->module_id, 'running', true, $page->activities, 'next-page');
            }
        });
        $partial = $this->cli('progression:evaluate-activities', $query);
        self::assertSame(1, $partial['code']);
        self::assertSame(2, $partial['report']['counts']['accepted']);
        self::assertStringNotContainsString('Private upstream', $partial['error']);
        $this->app->instance(FetchProgressionActivitiesPort::class, $provider);
        config()->set('database.connections.pgsql_lab5_lock', config('database.connections.pgsql_testing'));
        $mutex = new ProgressionExecutionMutex(DB::connection('pgsql_lab5_lock'));
        self::assertTrue($mutex->acquire());
        try {
            $locked = $this->cli('progression:close-windows');
            self::assertSame(0, $locked['code']);
            self::assertSame('locked', $locked['report']['status']);
        } finally {
            $mutex->release();
            DB::purge('pgsql_lab5_lock');
        }
        $distributionId = $evaluated['report']['ids']['distribution_ids'][0];
        $this->gatewayJson('GET', "/api/ib/v1/admin/progression-distributions/$distributionId")->assertOk()->assertJsonCount(2, 'data.beneficiaries');
        $this->gatewayJson('GET', "/api/ib/v1/admin/progression-distributions?plan_id=$planId&subscription_id={$first['id']}")->assertOk()->assertJsonCount(1, 'data');
        $faultId = (string) Str::uuid();
        $this->artisan('progression:lab-arm-failure', ['--id' => $faultId, '--context' => $this->context, '--operation' => 'close', '--stage' => 'before_result_finalize', '--subscription' => $first['id']])->assertExitCode(0);
        $this->artisan('progression:lab-arm-failure', ['--id' => (string) Str::uuid(), '--context' => $this->context, '--operation' => 'close', '--stage' => 'before_placement', '--subscription' => $second['id']])->assertExitCode(0);
        $this->advance(2, '2026-09-11T00:59:59Z');
        $this->cli('progression:close-windows');
        self::assertSame(0, DB::table('progression_runs')->where('window_starts_at', '2026-09-10T00:00:00Z')->count());
        $this->advance(3, '2026-09-11T01:00:00Z');
        $closed = $this->cli('progression:close-windows');
        self::assertSame(1, $closed['code']);
        self::assertSame(1, $closed['report']['counts']['results_failed']);
        self::assertSame(1, $closed['report']['counts']['placements_failed']);
        $run = DB::table('progression_runs')->where('window_starts_at', '2026-09-10T00:00:00Z')->first();
        $result = DB::table('progression_run_results')->where('run_id', $run->id)->where('subscription_id', $first['id'])->first();
        self::assertSame('120.00000000', $result->total_points);
        self::assertSame($gold['id'], $result->target_program_id);
        self::assertNotNull($result->decision_at);
        $snapshot = json_decode($run->snapshot, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $snapshot['participants']);
        self::assertCount(1, $snapshot['participants'][0]['contribution_ids']);
        $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}/results")->assertOk()->assertJsonCount(2, 'data');
        $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}/results/{$result->id}")->assertOk()->assertJsonPath('data.failure_code', 'retryable_failure');
        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/$planId/programs/{$gold['id']}", ['entry_threshold' => 150, 'lock_version' => $gold['lock_version']])->assertOk();
        $this->artisan('progression:lab-arm-failure', ['--id' => $faultId, '--context' => $this->context, '--operation' => 'close', '--stage' => 'before_result_finalize', '--subscription' => $first['id']])->assertExitCode(0);
        app(DomainClock::class)->end();
        $restart = new DomainClock;
        $restart->begin();
        self::assertSame(3, $restart->context()['sequence']);
        $restart->end();
        $recovered = $this->cli('progression:recover-runs', ['--run' => $run->id]);
        self::assertSame(0, $recovered['code']);
        self::assertSame(1, $recovered['report']['counts']['results_recovered']);
        self::assertSame(2, DB::table('progression_placement_applications')->whereIn('run_result_id', DB::table('progression_run_results')->where('run_id', $run->id)->pluck('id'))->count());
        $final = $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}/results/{$result->id}")->assertOk()->json('data');
        self::assertSame($result->id, $final['id']);
        self::assertSame('120.00000000', $final['total_points']);
        self::assertSame($gold['id'], $final['target_program_id']);
        self::assertSame('completed', $final['placement']['status']);
        foreach (['progression-distributions', 'progression-runs'] as $resource) {
            $this->fixtures[$resource.'-list'] = $this->gatewayJson('GET', '/api/ib/v1/admin/'.$resource)->assertOk()->json();
        }
        $this->fixtures['distribution-show'] = $this->gatewayJson('GET', "/api/ib/v1/admin/progression-distributions/$distributionId")->assertOk()->json();
        $this->fixtures['run-show'] = $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}")->assertOk()->json();
        $this->fixtures['results-list'] = $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}/results")->assertOk()->json();
        $this->fixtures['result-show'] = $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}/results/{$result->id}")->assertOk()->json();
        $this->fixtures['evaluations-list'] = $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations')->assertOk()->json();
        self::assertSame(0, $this->cli('progression:recover-runs', ['--run' => $run->id])['report']['counts']['results_recovered']);
        self::assertSame($base['id'], DB::table('progression_activity_evaluations')->where('subscription_id', $first['id'])->value('program_id'));
        $this->gatewayJson('GET', "/api/ib/v1/admin/progression-runs/{$run->id}")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->gatewayJson('GET', '/api/ib/v1/admin/progression-runs/'.Str::uuid()."/results/{$result->id}")->assertNotFound();
        $this->gatewayJson('GET', '/api/ib/v1/admin/progression-runs?per_page=101')->assertUnprocessable();
        $this->gatewayJson('GET', '/api/ib/v1/admin/progression-runs?window_from=invalid')->assertUnprocessable();
        $this->assertGatewayAuthGuards('GET', '/api/ib/v1/admin/progression-runs');
        $this->app->instance(FetchProgressionActivitiesPort::class, new class($moduleId, $source) implements FetchProgressionActivitiesPort
        {
            public function __construct(private readonly string $module, private readonly string $source) {}

            public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
            {
                return new FetchProgressionActivitiesResultData($this->module, 'running', true, [new NormalizedActivityData($this->module, 'late-deposit', $this->source, 'confirmed_deposit', 'usd', '9999', '2026-09-10T12:30:00Z')]);
            }
        });
        $late = $this->cli('progression:evaluate-activities', $query);
        self::assertSame(0, $late['code']);
        self::assertSame(2, $late['report']['counts']['excluded']);
        self::assertSame(2, DB::table('progression_contributions')->count());
        self::assertSame(2, DB::table('progression_activity_evaluations')->where('exclusion_reason', 'late_activity')->count());
        foreach (['paused' => 'deferred_module_paused', 'inactive' => 'rejected_module_inactive'] as $condition => $outcome) {
            $adapter = \Mockery::mock(FetchProgressionActivitiesPort::class);
            $adapter->shouldReceive('fetch')->once()->andReturn(new FetchProgressionActivitiesResultData($moduleId, $condition, false, []));
            $this->app->instance(FetchProgressionActivitiesPort::class, $adapter);
            $skipped = $this->cli('progression:evaluate-activities', $query);
            self::assertSame(0, $skipped['code']);
            self::assertSame($outcome, $skipped['report']['outcome']);
        }
    }
}
