<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesResultData;
use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesData;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesResult;
use App\Features\Progression\Enums\EvaluationStatus;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\UseCases\EvaluateProgressionActivitiesUseCase;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Contracts\Data\V1\PointsContributionContextData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextResultData;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Rules\Services\Strategies\PointsPerQuantityUnitStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
