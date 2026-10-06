<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlSubjectData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\DTOs\CaptureNegativePnlCutData;
use App\Features\Rewards\UseCases\CaptureNegativePnlCutUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NegativePnlCutSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_freezes_a_cut_and_reuses_it_without_querying_broker_again(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/Rewards/broker-negative-pnl-periods-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $row = $fixture['data'][0];
        Http::fake(['*' => Http::response(['data' => [$row], 'meta' => ['completed_subjects' => [$row['external_user_id']]]])]);
        $input = new CaptureNegativePnlCutData(
            (string) Str::uuid7(), (string) Str::uuid7(), $row['trading_account_id'], $row['server_group_id'], 'monthly',
            new ResolveNegativePnlPeriodsQueryData([new NegativePnlSubjectData($row['external_user_id'])], '2026-10-02T10:00:00Z', '2026-10-01T10:00:00Z'),
        );
        $useCase = app(CaptureNegativePnlCutUseCase::class);
        $first = $useCase->execute($input);
        $second = $useCase->execute($input);

        self::assertSame($first->identity_key, $second->identity_key);
        self::assertSame('125.5000000000', $second->period->npnl);
        self::assertSame(['position-positive'], $second->period->position_ids);
        self::assertSame(1, DB::table('negative_pnl_cut_snapshots')->count());
        Http::assertSentCount(1);
    }
}
