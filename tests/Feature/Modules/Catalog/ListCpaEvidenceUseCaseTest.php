<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Catalog\UseCases\ListCpaEvidenceUseCase;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ListCpaEvidenceUseCaseTest extends TestCase
{
    public function test_it_composes_closed_volume_and_exact_currency_certified_deposits(): void
    {
        config()->set('broker_catalog.base_url', 'http://broker.test');
        config()->set('finance.base_url', 'http://finance.test');
        Http::fake(static function (Request $request) {
            if (str_contains($request->url(), '/progression-activities')) {
                return Http::response([
                    'data' => [[
                        'source_activity_id' => 'broker:position:00000000-0000-7000-8000-000000000001',
                        'subject_external_user_id' => '11111111-1111-4111-8111-111111111111',
                        'metric_code' => 'closed_trading_volume', 'unit_code' => 'lot', 'quantity' => '1.50',
                        'occurred_at' => '2026-10-01T10:00:00Z',
                        'server_group_id' => '00000000-0000-7000-8000-000000000003',
                        'symbol_id' => '00000000-0000-7000-8000-000000000005',
                    ]], 'meta' => ['next_cursor' => null],
                ]);
            }

            return Http::response([
                'data' => [[
                    'id' => 71, 'amount_minor' => 12500, 'currency_code' => 'USD', 'minor_units' => 2,
                    'credited_at' => '2026-10-01T11:00:00Z',
                ], [
                    'id' => 72, 'amount_minor' => 1000, 'currency_code' => 'EUR', 'minor_units' => 2,
                    'credited_at' => '2026-10-01T11:00:00Z',
                ]], 'meta' => ['next_cursor' => null],
            ]);
        });
        $modules = new class implements ResolveModulesPort
        {
            public function findByIds(array $ids): array
            {
                return [new ModuleSummaryData($ids[0], 'broker', 'Broker', true, 'running')];
            }

            public function assertSelectable(array $ids): array
            {
                return $this->findByIds($ids);
            }
        };

        $evidence = $this->app->make(ListCpaEvidenceUseCase::class, ['modules' => $modules])->list(new ListCpaEvidenceQueryData(
            '00000000-0000-7000-8000-000000000010', '11111111-1111-4111-8111-111111111111',
            '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z', 'USD', 2,
            [['server_group_reference' => 'broker:server_group:00000000-0000-7000-8000-000000000003', 'symbol_reference' => 'broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005', 'currency_code' => 'USD']],
        ));

        self::assertSame('1.50', $evidence->volume_facts[0]->quantity);
        self::assertSame('finance:ledger:71', $evidence->deposit_facts[0]['source_activity_id']);
        self::assertCount(1, $evidence->deposit_facts);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/progression-activities')
            && $request->data()['external_user_id'] === '11111111-1111-4111-8111-111111111111'
            && $request->data()['instrument_references'][0] === 'broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005');
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/settled-external-deposits')
            && $request->data()['from'] === '2026-10-01T00:00:00+00:00'
            && $request->hasHeader('X-Internal-Source', 'mmt-ib-service'));
    }
}
