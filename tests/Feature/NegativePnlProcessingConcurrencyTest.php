<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Programs\Contracts\Data\V1\NegativePnlModuleConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlPaymentLevelData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Rewards\DTOs\NegativePnlFrozenInputsData;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlNegativePnlProcessingRepository;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NegativePnlProcessingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();
        parent::tearDown();
    }

    public function test_competing_workers_skip_locked_work_and_old_token_cannot_confirm_after_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03T00:00:00Z'));
        $group = new NegativePnlModuleConfigurationData((string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7(), [new NegativePnlPaymentLevelData(0, '0.1')]);
        $sub = new NegativePnlSubscriptionData((string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7(), now('UTC')->subDay()->toISOString(), null, null, null);
        $configuration = new NegativePnlProgramConfigurationData((string) Str::uuid7(), (string) Str::uuid7(), 'daily', (string) Str::uuid7(), $sub->activated_at, null, null, [$group]);
        $repo = new PostgreSqlNegativePnlProcessingRepository(DB::connection());
        $repo->seed($sub, $configuration, $group);
        $repo->seed($sub, $configuration, $group);
        self::assertSame(1, DB::table('negative_pnl_jobs')->count());
        config()->set('database.connections.pnl_worker', config('database.connections.pgsql_testing'));
        $competitor = DB::connection('pnl_worker');
        $other = new PostgreSqlNegativePnlProcessingRepository($competitor);
        try {
            DB::transaction(function () use ($other): void {
                DB::table('negative_pnl_jobs')->lockForUpdate()->first();
                self::assertNull($other->claim(now('UTC')->toISOString(), 60, []));
            });
            $first = $repo->claim(now('UTC')->toISOString(), 1, []);
            self::assertNull($other->claim(now('UTC')->toISOString(), 60, []));
            $this->travel(2)->seconds();
            $replacement = $other->claim(now('UTC')->toISOString(), 60, []);
            self::assertNotNull($replacement);
            self::assertNotSame($first->lease_token, $replacement->lease_token);
            $inputs = new NegativePnlFrozenInputsData(new SubscriptionContextData($sub->id, $sub->plan_id, $configuration->program_id, (string) Str::uuid7(), 'dynamic'), $group, $configuration->id, '0.01', []);
            $period = $other->beginPeriod($replacement, $inputs);
            $other->beginPeriod($replacement, $inputs);
            self::assertSame(1, DB::table('negative_pnl_periods')->count());
            $this->expectException(\RuntimeException::class);
            $repo->ready($first, $period->id);
        } finally {
            DB::purge('pnl_worker');
        }
    }
}
