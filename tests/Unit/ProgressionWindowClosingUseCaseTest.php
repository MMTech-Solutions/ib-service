<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Plans\Contracts\Data\V1\ProgressionPlanData;
use App\Features\Plans\Contracts\Ports\Input\ListActivePlansForProgressionPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Data\V1\ProgressionTargetProgramData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTargetProgramPort;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\Enums\ProgressionRunResultStatus;
use App\Features\Progression\Enums\ProgressionRunStatus;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Progression\Support\DeriveProgressionWindowFromPeriod;
use App\Features\Progression\UseCases\CloseProgressionWindowsUseCase;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Subscriptions\Contracts\Data\V1\ProgressionWindowSubscriptionData;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use App\Features\Subscriptions\Contracts\Ports\Input\ApplyProgressionPlacementPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListProgressionWindowSubscriptionsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Container;
use Mockery;
use Tests\TestCase;

final class ProgressionWindowClosingUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_closes_only_the_latest_window_after_the_global_margin_without_iam(): void
    {
        $repository = new class implements ProgressionRunRepositoryInterface
        {
            public ?ProgressionWindow $createdWindow = null;

            public function transaction(Closure $callback): mixed
            {
                return $callback();
            }

            public function latestWindowEndsAt(string $planId): ?CarbonImmutable
            {
                return null;
            }

            public function findOrCreateRun(string $planId, ProgressionWindow $window, CarbonImmutable $now): ProgressionRun
            {
                $this->createdWindow = $window;

                return new ProgressionRun('run', $planId, $window, ProgressionRunStatus::Pending, $now, null);
            }

            public function findRun(string $runId): ?ProgressionRun
            {
                return null;
            }

            public function findOrCreateResult(string $runId, string $subscriptionId, CarbonImmutable $now): ProgressionRunResult
            {
                return new ProgressionRunResult('result', $runId, $subscriptionId, ProgressionRunResultStatus::Failed, null, null, 0);
            }

            public function sumAcceptedContributionPoints(string $planId, string $subscriptionId, ProgressionWindow $window): ExactDecimal
            {
                return ExactDecimal::fromString('12.5');
            }

            public function markSkipped(ProgressionRunResult $result, CarbonImmutable $now): void {}

            public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void {}

            public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void {}

            public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun
            {
                return new ProgressionRun($run->id, $run->planId, $run->window, ProgressionRunStatus::Completed, $run->startedAt, $now);
            }

            public function finalizedResultsAwaitingPlacement(?string $runId = null): array
            {
                return [];
            }

            public function failedResults(?string $runId = null): array
            {
                return [];
            }

            public function lockFinalizedResultAwaitingPlacement(string $resultId): ?ProgressionRunResult
            {
                return null;
            }

            public function recordPlacementApplication(string $resultId, ProgressionPlacementOutcome $outcome, CarbonImmutable $now): void {}
        };
        $container = new Container;
        $container->instance('progression.runs.repositories.memory', $repository);
        $container->instance('progression.runs.repositories.postgresql', $repository);
        $plans = Mockery::mock(ListActivePlansForProgressionPort::class);
        $plans->shouldReceive('list')->once()->andReturn([new ProgressionPlanData('plan', 'daily')]);
        $subscriptions = Mockery::mock(ListProgressionWindowSubscriptionsPort::class);
        $subscriptions->shouldReceive('list')->once()->andReturn([new ProgressionWindowSubscriptionData('subscription', true)]);
        $targets = Mockery::mock(ResolveProgressionTargetProgramPort::class);
        $targets->shouldReceive('resolve')->once()->andReturn(new ProgressionTargetProgramData('program'));
        $iam = Mockery::mock(ResolveReferralUplinePort::class);
        $iam->shouldNotReceive('resolve');

        $gateways = new ProgressionInterFeatureGateways(
            Mockery::mock(FetchProgressionActivitiesPort::class), $iam, Mockery::mock(ResolvePlanContextPort::class), Mockery::mock(ResolvePlanSubscriptionContextPort::class), Mockery::mock(ResolvePlanProgressionContextPort::class), Mockery::mock(ResolveProgramContextPort::class), Mockery::mock(ResolveProgramSubscriptionContextPort::class), Mockery::mock(HasOpenSubscriptionsForPlanPort::class), Mockery::mock(ResolveSubscriptionContextPort::class), Mockery::mock(ResolvePointsContributionContextPort::class), $plans, $subscriptions, $targets, Mockery::mock(ApplyProgressionPlacementPort::class),
        );
        $useCase = new CloseProgressionWindowsUseCase(
            new ProgressionRunRepositoryFactory($container),
            $gateways,
            new DeriveProgressionWindowFromPeriod,
            new ApplyPendingProgressionPlacementsService($gateways),
        );

        $result = $useCase->execute(CarbonImmutable::parse('2026-09-20T01:00:00Z'));

        self::assertSame('2026-09-19T00:00:00.000000Z', $repository->createdWindow?->startsAt->toISOString());
        self::assertSame('2026-09-20T00:00:00.000000Z', $repository->createdWindow?->endsAt->toISOString());
        self::assertSame(1, $result->runs_completed);
        self::assertSame(1, $result->results_completed);
    }
}
