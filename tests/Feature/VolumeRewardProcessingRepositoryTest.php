<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlVolumeRewardProcessingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class VolumeRewardProcessingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_are_idempotent_and_expired_leases_can_be_reclaimed_with_backoff(): void
    {
        $module = ModuleRecord::factory()->create();
        $repository = new PostgreSqlVolumeRewardProcessingRepository(DB::connection());
        $event = new RecordVolumeRewardEventData($module->id, 'order-1', 'login-1', ['topic' => 'events']);
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
