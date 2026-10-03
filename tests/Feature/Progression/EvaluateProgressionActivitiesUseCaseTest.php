<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Programs\Contracts\Data\V1\ProgramProgressionConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramProgressionConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramProgressionConfigurationPort;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesResultData;
use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use App\Features\Progression\Contracts\Data\V1\ReferralUplineBeneficiaryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineResultData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesData;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesResult;
use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Factories\ActivityDistributionRepositoryFactory;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Models\ActivityDistribution;
use App\Features\Progression\UseCases\CloseProgressionWindowsUseCase;
use App\Features\Progression\UseCases\EvaluateProgressionActivitiesUseCase;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Contracts\Data\V1\PointsContributionContextData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextResultData;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Rules\Services\Strategies\PointsPerQuantityUnitStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class EvaluateProgressionActivitiesUseCaseTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private string $customerSub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->customerSub = $this->seedAuthorizedCustomer();
        config()->set('progression.repository', 'postgresql');
        $this->app->instance(ResolveReferralUplinePort::class, new class implements ResolveReferralUplinePort
        {
            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                return ResolveReferralUplineResultData::resolved([
                    new ReferralUplineBeneficiaryData($query->source_external_user_id, 0),
                ], CarbonImmutable::now('UTC')->toISOString());
            }
        });
    }

    public function test_it_accepts_eligible_activity_and_is_idempotent(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'dep-001',
            beneficiary: $this->customerSub,
            quantity: '100',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $useCase = $this->app->make(EvaluateProgressionActivitiesUseCase::class);
        $first = $useCase->execute(new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T00:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
        ));

        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_EVALUATED, $first->outcome);
        self::assertCount(1, $first->evaluations);
        self::assertTrue($first->evaluations[0]->isAccepted());
        self::assertSame('10', $first->evaluations[0]->contribution?->points->value());
        self::assertSame($fixture['subscription_id'], $first->evaluations[0]->subscriptionId);

        $second = $useCase->execute(new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T00:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
        ));

        self::assertCount(1, $second->evaluations);
        self::assertSame($first->evaluations[0]->id, $second->evaluations[0]->id);
        self::assertSame('10', $second->evaluations[0]->contribution?->points->value());

        $stored = $this->app->make(ActivityEvaluationRepositoryFactory::class)->make()
            ->findByIdempotencyKey($fixture['module_id'], 'dep-001', $this->customerSub);
        self::assertNotNull($stored);
        self::assertSame($first->evaluations[0]->id, $stored->id);

        CarbonImmutable::setTestNow();
    }

    public function test_local_activity_contribution_window_and_placement_flow_is_idempotent(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1', period: 'daily');
        $advanced = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs", [
            'code' => 'advanced', 'name' => 'Advanced', 'entry_threshold' => 10, 'module_ids' => [$fixture['module_id']],
        ])->assertCreated()->json('data');
        $activity = $this->activity($fixture['module_id'], 'local-e2e-deposit', $this->customerSub, '100', 'usd', '2026-09-10T12:00:00Z');
        $this->stubActivities([$activity]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00Z'));
        try {
            $evaluator = app(EvaluateProgressionActivitiesUseCase::class);
            $query = new EvaluateProgressionActivitiesData($fixture['plan_id'], $fixture['module_id'], '2026-09-10T00:00:00Z', '2026-09-11T00:00:00Z');
            $first = $evaluator->execute($query);
            $retry = $evaluator->execute($query);
            self::assertSame($first->evaluations[0]->id, $retry->evaluations[0]->id);
            self::assertSame('10', $first->evaluations[0]->contribution->points->value());
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-11T01:00:00Z'));
            $closer = app(CloseProgressionWindowsUseCase::class);
            $closed = $closer->execute();
            self::assertSame(1, $closed->results_completed);
            self::assertSame(1, DB::table('progression_contributions')->count());
            self::assertSame($advanced['id'], DB::table('progression_run_results')->first()->target_program_id);
            self::assertSame($advanced['id'], DB::table('subscription_placements')->where('subscription_id', $fixture['subscription_id'])->whereNull('effective_until')->value('program_id'));
            self::assertSame(0, $closer->execute()->results_completed);
            self::assertSame(1, DB::table('progression_placement_applications')->count());
            self::assertSame($fixture['program_id'], DB::table('progression_activity_evaluations')->first()->program_id);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_creates_independent_evaluations_for_each_frozen_beneficiary(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $secondCustomer = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbb2';
        $this->seedAuthorizedCustomer($secondCustomer);
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $fixture['plan_id']], $secondCustomer)
            ->assertCreated();
        $this->app->instance(ResolveReferralUplinePort::class, new class($this->customerSub, $secondCustomer) implements ResolveReferralUplinePort
        {
            public function __construct(private readonly string $first, private readonly string $second) {}

            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                return ResolveReferralUplineResultData::resolved([
                    new ReferralUplineBeneficiaryData($this->first, 0),
                    new ReferralUplineBeneficiaryData($this->second, 1),
                ], '2026-09-10T12:30:00.000000Z');
            }
        });
        $this->stubActivities([$this->activity($fixture['module_id'], 'network-many', (string) Str::uuid7(), '100', 'usd', '2026-09-10T12:00:00.000000Z')]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $result = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));

        self::assertCount(2, $result->evaluations);
        self::assertSame([0, 1], array_map(fn ($evaluation): int => $evaluation->distributionLevel, $result->evaluations));
        self::assertSame(['10', '10'], array_map(fn ($evaluation): string => $evaluation->contribution?->points->value() ?? '', $result->evaluations));
        CarbonImmutable::setTestNow();
    }

    public function test_it_reuses_an_empty_or_existing_distribution_without_calling_iam(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $source = (string) Str::uuid7();
        $distributions = $this->app->make(ActivityDistributionRepositoryFactory::class)->make();
        $distributions->record(ActivityDistribution::resolve((string) Str::uuid7(), $fixture['module_id'], 'network-empty', $source, CarbonImmutable::parse('2026-09-10T12:30:00Z'), []));
        $this->app->instance(ResolveReferralUplinePort::class, new class implements ResolveReferralUplinePort
        {
            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                throw new \RuntimeException('IAM must not be called for a snapshot.');
            }
        });
        $this->stubActivities([$this->activity($fixture['module_id'], 'network-empty', $source, '100', 'usd', '2026-09-10T12:00:00.000000Z')]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $result = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));

        self::assertSame([], $result->evaluations);
        self::assertSame([], $result->retryableFailures);
        CarbonImmutable::setTestNow();
    }

    public function test_it_leaves_iam_failures_retryable_without_a_distribution(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $source = (string) Str::uuid7();
        $this->app->instance(ResolveReferralUplinePort::class, new class implements ResolveReferralUplinePort
        {
            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                return ResolveReferralUplineResultData::failed('unavailable');
            }
        });
        $this->stubActivities([$this->activity($fixture['module_id'], 'network-failure', $source, '100', 'usd', '2026-09-10T12:00:00.000000Z')]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $result = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));

        self::assertSame([], $result->evaluations);
        self::assertSame('unavailable', $result->retryableFailures[0]->failure_code);
        self::assertNull($this->app->make(ActivityDistributionRepositoryFactory::class)->make()->findBySourceActivity($fixture['module_id'], 'network-failure'));
        CarbonImmutable::setTestNow();
    }

    public function test_a_beneficiary_failure_does_not_revert_other_final_evaluations(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $secondCustomer = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbb3';
        $this->seedAuthorizedCustomer($secondCustomer);
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', ['plan_id' => $fixture['plan_id']], $secondCustomer)->assertCreated();
        $this->app->instance(ResolveReferralUplinePort::class, new class($this->customerSub, $secondCustomer) implements ResolveReferralUplinePort
        {
            public function __construct(private readonly string $first, private readonly string $second) {}

            public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
            {
                return ResolveReferralUplineResultData::resolved([new ReferralUplineBeneficiaryData($this->first, 0), new ReferralUplineBeneficiaryData($this->second, 1)], '2026-09-10T12:30:00Z');
            }
        });
        $templateId = (string) Str::uuid7();
        $templateVersionId = (string) Str::uuid7();
        $bindingId = (string) Str::uuid7();
        $configurationId = (string) Str::uuid7();
        $timestamp = '2026-09-10T10:00:00Z';
        DB::table('progression_templates')->insert(['id' => $templateId, 'name' => 'Network '.Str::random(6), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        DB::table('progression_template_versions')->insert(['id' => $templateVersionId, 'template_id' => $templateId, 'version_number' => 1, 'status' => 'published', 'published_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        DB::table('plan_progression_template_version_bindings')->insert(['id' => $bindingId, 'plan_id' => $fixture['plan_id'], 'template_version_id' => $templateVersionId, 'created_at' => $timestamp]);
        DB::table('program_symbol_configurations')->insert(['id' => $configurationId, 'program_id' => $fixture['program_id'], 'module_id' => $fixture['module_id'], 'symbol_reference' => 'XAUUSD', 'server_group_reference' => 'default', 'currency_code' => 'USD', 'use_for_progression' => true, 'plan_progression_template_version_binding_id' => $bindingId, 'use_for_volume_reward' => false, 'use_for_cpa' => false, 'starts_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        $configuration = new ProgramProgressionConfigurationData($configurationId, $bindingId, $templateVersionId, '1');
        $this->app->instance(ResolveProgramProgressionConfigurationPort::class, new class($configuration) implements ResolveProgramProgressionConfigurationPort
        {
            public function __construct(private readonly ProgramProgressionConfigurationData $configuration) {}

            public function resolve(ResolveProgramProgressionConfigurationQueryData $query): ?ProgramProgressionConfigurationData
            {
                if ($query->distribution_level === 1) {
                    throw new \RuntimeException('temporary configuration outage');
                }

                return $this->configuration;
            }
        });
        $activity = new NormalizedActivityData($fixture['module_id'], 'network-partial', (string) Str::uuid7(), 'confirmed_deposit', 'usd', '100', '2026-09-10T12:00:00Z', 'XAUUSD');
        $this->stubActivities([$activity]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00Z'));

        $result = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));

        self::assertCount(1, $result->evaluations);
        self::assertTrue($result->evaluations[0]->isAccepted());
        self::assertSame('beneficiary_evaluation_failed', $result->retryableFailures[0]->failure_code);
        CarbonImmutable::setTestNow();
    }

    public function test_it_excludes_catalog_reasons_for_ineligible_activity(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'no-sub',
            beneficiary: (string) Str::uuid7(),
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);
        $noSub = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(ExclusionReason::NoActiveSubscription, $noSub->evaluations[0]->exclusionReason);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T14:00:00.000000Z'));
        $fixedSubscription = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$fixture['subscription_id']}/placement/fix", [
            'program_id' => $fixture['program_id'],
            'lock_version' => $fixture['subscription_lock_version'],
        ])->assertOk()->json('data');

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'fixed',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T14:30:00.000000Z',
        )]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T15:00:00.000000Z'));
        $fixed = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute(new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T14:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
        ));
        self::assertCount(1, $fixed->evaluations, $fixed->outcome);
        self::assertSame(ExclusionReason::PlacementFixed, $fixed->evaluations[0]->exclusionReason);

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$fixture['subscription_id']}/placement/release", [
            'lock_version' => $fixedSubscription['lock_version'],
        ])->assertOk();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));
        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'wrong-unit',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'lot',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);
        $wrongUnit = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(ExclusionReason::NoApplicableRule, $wrongUnit->evaluations[0]->exclusionReason);

        $this->app->instance(ResolvePointsContributionContextPort::class, new class implements ResolvePointsContributionContextPort
        {
            public function resolve(ResolvePointsContributionContextQueryData $query): ResolvePointsContributionContextResultData
            {
                return ResolvePointsContributionContextResultData::foundContext(new PointsContributionContextData(
                    rule_id: (string) Str::uuid7(),
                    rule_version_id: (string) Str::uuid7(),
                    rule_assignment_id: (string) Str::uuid7(),
                    strategy_type: PointsPerQuantityUnitStrategy::TYPE,
                    scope_type: 'all',
                    unit: 'eur',
                    weight: '1',
                ));
            }
        });
        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'unit-mismatch',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);
        $mismatch = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(ExclusionReason::UnitMismatch, $mismatch->evaluations[0]->exclusionReason);

        CarbonImmutable::setTestNow();
    }

    public function test_it_excludes_scale_exceeded_and_skips_inactive_plan(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));

        $this->app->instance(ResolvePointsContributionContextPort::class, new class implements ResolvePointsContributionContextPort
        {
            public function resolve(ResolvePointsContributionContextQueryData $query): ResolvePointsContributionContextResultData
            {
                return ResolvePointsContributionContextResultData::foundContext(new PointsContributionContextData(
                    rule_id: (string) Str::uuid7(),
                    rule_version_id: (string) Str::uuid7(),
                    rule_assignment_id: (string) Str::uuid7(),
                    strategy_type: PointsPerQuantityUnitStrategy::TYPE,
                    scope_type: 'all',
                    unit: 'usd',
                    weight: '1.123456789',
                ));
            }
        });
        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'scale',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);
        $scale = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(ExclusionReason::ScaleExceeded, $scale->evaluations[0]->exclusionReason);

        $plan = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$fixture['plan_id']}")->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/deactivate", [
            'reason' => 'Pause progression',
            'lock_version' => $plan['lock_version'],
        ])->assertOk();

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'after-deactivate',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);
        $skipped = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_SKIPPED_PLAN_INACTIVE, $skipped->outcome);
        self::assertSame([], $skipped->evaluations);

        CarbonImmutable::setTestNow();
    }

    public function test_it_defers_while_module_paused_and_excludes_closed_window_after_resume(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1', period: 'daily');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-11T13:00:00.000000Z'));

        $this->app->instance(FetchProgressionActivitiesPort::class, new class($fixture['module_id']) implements FetchProgressionActivitiesPort
        {
            public function __construct(private readonly string $moduleId) {}

            public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
            {
                return new FetchProgressionActivitiesResultData(
                    module_id: $this->moduleId,
                    module_condition: 'paused',
                    provider_invoked: true,
                    activities: [],
                );
            }
        });

        $deferred = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_DEFERRED_MODULE_PAUSED, $deferred->outcome);

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'paused-closed',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);

        $closed = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute(new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T00:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
            resuming_after_pause: true,
        ));
        self::assertSame(EvaluationStatus::Excluded, $closed->evaluations[0]->status);
        self::assertSame(ExclusionReason::WindowClosedAfterPause, $closed->evaluations[0]->exclusionReason);

        CarbonImmutable::setTestNow();
    }

    public function test_it_excludes_when_module_not_selected_on_program(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');
        $program = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs/{$fixture['program_id']}")
            ->assertOk()
            ->json('data');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/programs/{$fixture['program_id']}", [
            'module_ids' => [],
            'lock_version' => $program['lock_version'],
        ])->assertOk();

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'not-selected',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T12:00:00.000000Z',
        )]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T13:00:00.000000Z'));
        $result = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));

        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_EVALUATED, $result->outcome);
        self::assertCount(1, $result->evaluations);
        self::assertSame(ExclusionReason::ModuleNotSelected, $result->evaluations[0]->exclusionReason);
        self::assertSame($fixture['subscription_id'], $result->evaluations[0]->subscriptionId);
        self::assertSame($fixture['program_id'], $result->evaluations[0]->programId);

        CarbonImmutable::setTestNow();
    }

    public function test_plan_reactivation_does_not_backfill_inactive_period_and_does_not_mutate_placement(): void
    {
        $fixture = $this->createEvaluationFixture(unit: 'usd', weight: '0.1');

        $placementBefore = DB::table('subscription_placements')
            ->where('subscription_id', $fixture['subscription_id'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        self::assertNotNull($placementBefore);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T14:00:00.000000Z'));
        $plan = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$fixture['plan_id']}")->assertOk()->json('data');
        $deactivated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/deactivate", [
            'reason' => 'Halt progression',
            'lock_version' => $plan['lock_version'],
        ])->assertOk()->json('data');

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'while-inactive',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T14:30:00.000000Z',
        )]);
        $skipped = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_SKIPPED_PLAN_INACTIVE, $skipped->outcome);
        self::assertSame([], $skipped->evaluations);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T15:00:00.000000Z'));
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$fixture['plan_id']}/activate", [
            'reason' => 'Resume progression',
            'lock_version' => $deactivated['lock_version'],
        ])->assertOk();

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'while-inactive',
            beneficiary: $this->customerSub,
            quantity: '10',
            unit: 'usd',
            occurredAt: '2026-09-10T14:30:00.000000Z',
        )]);
        $backfill = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute($this->command($fixture));
        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_EVALUATED, $backfill->outcome);
        self::assertCount(1, $backfill->evaluations);
        self::assertTrue($backfill->evaluations[0]->isExcluded());
        self::assertSame(ExclusionReason::PlanInactive, $backfill->evaluations[0]->exclusionReason);
        self::assertNull($backfill->evaluations[0]->contribution);

        $this->stubActivities([$this->activity(
            moduleId: $fixture['module_id'],
            sourceActivityId: 'after-reactivation',
            beneficiary: $this->customerSub,
            quantity: '100',
            unit: 'usd',
            occurredAt: '2026-09-10T15:30:00.000000Z',
        )]);
        $accepted = $this->app->make(EvaluateProgressionActivitiesUseCase::class)->execute(new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T15:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
        ));
        self::assertSame(EvaluateProgressionActivitiesResult::OUTCOME_EVALUATED, $accepted->outcome);
        self::assertCount(1, $accepted->evaluations);
        self::assertTrue($accepted->evaluations[0]->isAccepted());
        self::assertSame('10', $accepted->evaluations[0]->contribution?->points->value());

        $placementAfter = DB::table('subscription_placements')
            ->where('subscription_id', $fixture['subscription_id'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        self::assertNotNull($placementAfter);
        self::assertSame($placementBefore->id, $placementAfter->id);
        self::assertSame($placementBefore->program_id, $placementAfter->program_id);
        self::assertSame($placementBefore->is_fixed, $placementAfter->is_fixed);
        self::assertSame(
            1,
            DB::table('subscription_placements')->where('subscription_id', $fixture['subscription_id'])->count(),
        );

        CarbonImmutable::setTestNow();
    }

    /**
     * @param  list<NormalizedActivityData>  $activities
     */
    private function stubActivities(array $activities, string $condition = 'running'): void
    {
        $moduleId = $activities[0]->module_id ?? (string) Str::uuid7();

        $this->app->instance(FetchProgressionActivitiesPort::class, new class($moduleId, $activities, $condition) implements FetchProgressionActivitiesPort
        {
            /**
             * @param  list<NormalizedActivityData>  $activities
             */
            public function __construct(
                private readonly string $moduleId,
                private readonly array $activities,
                private readonly string $condition,
            ) {}

            public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData
            {
                return new FetchProgressionActivitiesResultData(
                    module_id: $this->moduleId,
                    module_condition: $this->condition,
                    provider_invoked: true,
                    activities: $this->activities,
                );
            }
        });
    }

    private function activity(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiary,
        string $quantity,
        string $unit,
        string $occurredAt,
    ): NormalizedActivityData {
        return new NormalizedActivityData(
            module_id: $moduleId,
            source_activity_id: $sourceActivityId,
            subject_external_user_id: $beneficiary,
            metric_code: 'confirmed_deposit',
            unit_code: $unit,
            quantity: $quantity,
            occurred_at: $occurredAt,
        );
    }

    /**
     * @param  array{plan_id: string, module_id: string}  $fixture
     */
    private function command(array $fixture): EvaluateProgressionActivitiesData
    {
        return new EvaluateProgressionActivitiesData(
            plan_id: $fixture['plan_id'],
            module_id: $fixture['module_id'],
            occurred_from: '2026-09-10T00:00:00.000000Z',
            occurred_until: '2026-09-11T00:00:00.000000Z',
        );
    }

    /**
     * @return array{
     *     plan_id: string,
     *     program_id: string,
     *     module_id: string,
     *     subscription_id: string,
     *     subscription_lock_version: int
     * }
     */
    private function createEvaluationFixture(string $unit, string $weight, string $period = 'monthly'): array
    {
        $moduleId = $this->brokerId();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10T10:00:00.000000Z'));

        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'eval-'.Str::lower(Str::random(6)),
            'name' => 'Eval Plan',
            'module_ids' => [$moduleId],
            'progression_period' => $period,
            'requires_approval' => false,
        ])->assertCreated()->json('data');
        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/activate", [
            'reason' => 'Ready',
            'lock_version' => $plan['lock_version'],
        ])->assertOk()->json('data');
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'entry_threshold' => 0,
            'module_ids' => [$moduleId],
        ])->assertCreated()->json('data');

        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/rules", [
            'name' => 'Deposit Points',
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
        ])->assertCreated()->json('data');
        $draft = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/rules/{$rule['id']}/versions", [
            'schema_version' => 1,
            'configuration' => [
                'unit' => $unit,
                'points_per_unit' => $weight,
            ],
        ])->assertCreated()->json('data');
        $version = $this->gatewayJson(
            'POST',
            "/api/ib/v1/admin/plans/{$activated['id']}/rules/{$rule['id']}/versions/{$draft['id']}/publish",
            ['lock_version' => $draft['lock_version']],
        )->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/rules/{$rule['id']}/assignments", [
            'program_id' => $program['id'],
            'module_id' => $moduleId,
            'rule_version_id' => $version['id'],
        ])->assertCreated();

        $subscription = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $activated['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        return [
            'plan_id' => $activated['id'],
            'program_id' => $program['id'],
            'module_id' => $moduleId,
            'subscription_id' => $subscription['id'],
            'subscription_lock_version' => $subscription['lock_version'],
        ];
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
