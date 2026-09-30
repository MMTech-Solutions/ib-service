<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Sources\Broker\Services\Adapters\BrokerClosedTradingVolumeActivityAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BrokerClosedTradingVolumeActivityAdapterTest extends TestCase
{
    public function test_it_maps_the_broker_closed_volume_contract_without_changing_the_modules_activity_shape(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [[
                    'source_activity_id' => 'broker:position:00000000-0000-7000-8000-000000000001',
                    'subject_external_user_id' => '11111111-1111-4111-8111-111111111111',
                    'metric_code' => 'closed_trading_volume',
                    'unit_code' => 'lot',
                    'quantity' => '1.50',
                    'occurred_at' => '2026-09-10T10:00:00Z',
                    'server_group_id' => '00000000-0000-7000-8000-000000000003',
                    'symbol_id' => '00000000-0000-7000-8000-000000000005',
                ]],
                'meta' => ['next_cursor' => null],
            ]),
        ]);

        $activities = $this->app->make(BrokerClosedTradingVolumeActivityAdapter::class)->fetchPage(
            '00000000-0000-7000-8000-000000000010',
            CarbonImmutable::parse('2026-09-10T00:00:00Z'),
            CarbonImmutable::parse('2026-09-11T00:00:00Z'),
            null,
            null,
            100,
        );

        self::assertCount(1, $activities);
        self::assertSame('broker:position:00000000-0000-7000-8000-000000000001', $activities[0]->source_activity_id);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005', $activities[0]->instrument_reference);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/api/broker/v1/internal/progression-activities')
            && $request->data()['limit'] === 100
            && $request->hasHeader('X-Internal-Source', 'mmt-ib-service'));
    }
}
