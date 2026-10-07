<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\DTOs\VolumeRewardPreparedInputsData;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlVolumeRewardProcessingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VolumeRewardProcessingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_reuses_frozen_activity_distribution_and_preparation_after_failure(): void
    {
        $module = ModuleRecord::factory()->create();
        $repo = new PostgreSqlVolumeRewardProcessingRepository(DB::connection());
        $now = now('UTC')->toImmutable();
        $activity = new VolumeRewardActivityData($module->id, 'position', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lot', '2', $now->toISOString(), 'symbol', 'USD', 2, null);
        $first = $repo->claimEvaluation($activity, $now, $now->addMinute());
        self::assertNull($repo->claimEvaluation($activity, $now, $now->addMinute()));
        $repo->freezeDistribution($first, ResolveRewardUplineResultData::resolved([], $now->toISOString()));
        $repo->freezePreparation($first, 'event', new VolumeRewardPreparedInputsData([], 1, ['beneficiary' => 'no_applicable_rule']));
        try {
            $repo->evaluationTransaction($first, function () use ($repo, $activity): void {
                $repo->recordEvent(new RecordVolumeRewardEventData((string) Str::uuid7(), 1, $activity, []));
                throw new \RuntimeException('injected_failure');
            });
            self::fail('Expected local rollback');
        } catch (\RuntimeException $exception) {
            self::assertSame('injected_failure', $exception->getMessage());
        }
        self::assertSame(0, DB::table('volume_reward_event_receipts')->count());
        $repo->releaseEvaluation($first);
        $recovered = $repo->claimEvaluation($activity, $now, $now->addMinute());
        self::assertSame('2', $recovered->activity->quantity);
        self::assertSame([], $recovered->distribution->beneficiaries);
        self::assertSame(['beneficiary' => 'no_applicable_rule'], $recovered->preparations['event']->outcomes);
        $repo->freezePreparation($recovered, 'event', new VolumeRewardPreparedInputsData([], 9));
        $repo->releaseEvaluation($recovered);
        self::assertSame(1, $repo->claimEvaluation($activity, $now, $now->addMinute())->preparations['event']->skipped);
    }

    public function test_expired_evaluation_worker_cannot_confirm_effects(): void
    {
        $module = ModuleRecord::factory()->create();
        $repo = new PostgreSqlVolumeRewardProcessingRepository(DB::connection());
        $now = now('UTC')->toImmutable();
        $activity = new VolumeRewardActivityData($module->id, 'position', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lot', '2', $now->toISOString(), 'symbol', 'USD', 2, null);
        $first = $repo->claimEvaluation($activity, $now, $now->addSecond());
        $this->travel(2)->seconds();
        $replacement = $repo->claimEvaluation($activity, now('UTC')->toImmutable(), now('UTC')->addMinute()->toImmutable());
        self::assertNotSame($first->lease_token, $replacement->lease_token);
        $this->expectException(\RuntimeException::class);
        $repo->recordEvaluationOutcome($first, 'event', 'completed');
    }

    public function test_receipts_are_idempotent_and_expired_leases_can_be_reclaimed_with_backoff(): void
    {
        $module = ModuleRecord::factory()->create();
        $repository = new PostgreSqlVolumeRewardProcessingRepository(DB::connection());
        $activity = new VolumeRewardActivityData($module->id, 'position', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lot', '2', '2026-10-02T12:00:00Z', 'symbol', 'USD', 2, '3');
        $event = new RecordVolumeRewardEventData((string) Str::uuid7(), 1, $activity, ['topic' => 'events']);
        $now = CarbonImmutable::parse('2026-10-02T12:00:00+00:00');
        CarbonImmutable::setTestNow($now);
        $repository->recordEvent($event);
        $repository->recordEvent($event);

        self::assertSame(1, DB::table('volume_reward_event_receipts')->count());
        $first = $repository->claimNextEvent($now, $now->addMinute());
        self::assertNotNull($first);
        self::assertSame(1, $first->attempt_count);
        self::assertNull($repository->claimNextEvent($now->addSeconds(30), $now->addMinutes(2)));

        $reclaimed = $repository->claimNextEvent($now->addMinute(), $now->addMinutes(2));
        self::assertNotNull($reclaimed);
        self::assertSame(2, $reclaimed->attempt_count);
        self::assertNotSame($first->claim_token, $reclaimed->claim_token);
        $repository->markEventRetryable($reclaimed->id, $reclaimed->claim_token, 'broker_unavailable', $now->addMinutes(3));
        self::assertNull($repository->claimNextEvent($now->addMinutes(2), $now->addMinutes(4)));
        self::assertNotNull($repository->claimNextEvent($now->addMinutes(3), $now->addMinutes(4)));
    }

    public function test_periodic_cursor_advances_only_after_a_completed_page(): void
    {
        $module = ModuleRecord::factory()->create();
        $repository = new PostgreSqlVolumeRewardProcessingRepository(DB::connection());
        $from = '2026-10-01T00:00:00+00:00';
        $until = CarbonImmutable::parse('2026-10-02T00:00:00+00:00');
        $run = $repository->claimPeriodicRun($module->id, $from, $until, $until->addMinute());
        self::assertNotNull($run);
        self::assertSame(1, $run->attempt_count);

        $repository->completePeriodicPage($run->id, $run->claim_token, 'cursor-2', $until);
        $nextPage = $repository->claimPeriodicRun($module->id, $from, $until, $until->addMinute());
        self::assertNotNull($nextPage);
        self::assertSame('cursor-2', $nextPage->cursor);
        self::assertSame(2, $nextPage->attempt_count);

        $repository->completePeriodicPage($nextPage->id, $nextPage->claim_token, null, $until);
        $nextUntil = $until->addDay();
        $nextRun = $repository->claimPeriodicRun($module->id, $from, $nextUntil, $nextUntil->addMinute());
        self::assertNotNull($nextRun);
        self::assertSame($until->toDateTimeString(), CarbonImmutable::parse($nextRun->occurred_from)->utc()->toDateTimeString());
    }
}
