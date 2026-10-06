<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlBaselineData;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlSubjectData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\HistoricalPnlCoverageUnavailableException;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BrokerNegativePnlPeriodsAdapterTest extends TestCase
{
    public function test_it_maps_the_v1_broker_contract_and_sends_baselines(): void
    {
        Http::fake(['*' => Http::response($this->fixture())]);

        $result = $this->app->make(ResolveNegativePnlPeriodsPort::class)->resolve(
            new ResolveNegativePnlPeriodsQueryData(
                subjects: [new NegativePnlSubjectData('11111111-1111-4111-8111-111111111111', [new NegativePnlBaselineData(
                    account_id: '00000000-0000-7000-8000-000000000002',
                    balance_after: '100.00',
                    occurred_until: '2026-10-01T10:00:00Z',
                )])],
                occurred_until: '2026-10-02T10:00:00Z',
            ),
        );

        self::assertCount(2, $result->periods);
        self::assertTrue($result->periods[0]->establishesBaseline());
        self::assertNull($result->periods[0]->net_pnl);
        self::assertSame('-40.00', $result->periods[1]->net_pnl);
        self::assertSame('2026-10-01T10:00:00.000000Z', $result->periods[1]->occurred_from);
        self::assertSame(
            ['00000000-0000-7000-8000-000000000101'],
            $result->periods[1]->evidence->external_deposit_references,
        );

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/broker/v1/internal/accounts/negative-pnl-periods/resolve')
            && $request->hasHeader('X-Internal-Source', 'mmt-ib-service')
            && $request->data()['subjects'][0]['external_user_id'] === '11111111-1111-4111-8111-111111111111'
            && $request->data()['subjects'][0]['baselines'][0]['balance_after'] === '100.00');
    }

    public function test_it_classifies_conflict_and_server_errors_as_recoverable(): void
    {
        foreach ([409, 500] as $status) {
            Http::fake(['*' => Http::response([], $status)]);

            try {
                $this->resolveEmpty();
                self::fail("Expected recoverable exception for HTTP {$status}.");
            } catch (NegativePnlPeriodsUnavailableException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_classifies_transport_errors_as_recoverable(): void
    {
        Http::fake(static fn () => Http::failedConnection());

        $this->expectException(NegativePnlPeriodsUnavailableException::class);

        $this->resolveEmpty();
    }

    public function test_it_rejects_invalid_success_and_other_client_errors_permanently(): void
    {
        Http::fake(['*' => Http::response(['data' => [['status' => 'resolved']]], 200)]);

        try {
            $this->resolveEmpty();
            self::fail('Expected invalid response exception.');
        } catch (InvalidNegativePnlPeriodsResponseException) {
            self::assertTrue(true);
        }

        Http::fake(['*' => Http::response([], 422)]);
        $this->expectException(InvalidNegativePnlPeriodsResponseException::class);
        $this->resolveEmpty();
    }

    public function test_rewards_provider_registers_the_negative_pnl_port(): void
    {
        self::assertInstanceOf(
            ResolveNegativePnlPeriodsPort::class,
            $this->app->make(ResolveNegativePnlPeriodsPort::class),
        );
    }

    public function test_it_maps_historical_read_evidence_and_sends_the_requested_cut(): void
    {
        $fixture = $this->fixture();
        foreach ($fixture['data'] as &$row) {
            $row['balance_read_id'] = '123';
            $row['balance_read_at'] = '2026-10-02T09:58:00Z';
        }
        unset($row);
        Http::fake(['*' => Http::response($fixture)]);

        $result = $this->app->make(ResolveNegativePnlPeriodsPort::class)->resolve(new ResolveNegativePnlPeriodsQueryData(
            [new NegativePnlSubjectData('11111111-1111-4111-8111-111111111111', [new NegativePnlBaselineData('00000000-0000-7000-8000-000000000002', '100.00', '2026-10-01T10:00:00Z')])],
            '2026-10-02T10:00:00Z',
        ));

        self::assertSame('123', $result->periods[1]->balance_read_id);
        Http::assertSent(fn (Request $request): bool => $request['occurred_until'] === '2026-10-02T10:00:00Z');
    }

    public function test_it_rejects_a_read_after_the_cut(): void
    {
        $fixture = $this->fixture();
        $fixture['data'][0]['balance_read_id'] = '123';
        $fixture['data'][0]['balance_read_at'] = '2026-10-02T10:00:01Z';
        Http::fake(['*' => Http::response($fixture)]);

        $this->expectException(InvalidNegativePnlPeriodsResponseException::class);
        $this->resolveEmpty();
    }

    public function test_it_distinguishes_missing_historical_coverage(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'HISTORICAL_PNL_COVERAGE_UNAVAILABLE']], 422)]);
        $this->expectException(HistoricalPnlCoverageUnavailableException::class);
        $this->resolveEmpty();
    }

    public function test_empty_completed_subject_is_valid_but_missing_or_duplicate_completion_is_invalid(): void
    {
        $id = '11111111-1111-4111-8111-111111111111';
        Http::fake(['*' => Http::sequence()->push(['data' => [], 'meta' => ['completed_subjects' => [$id]]])->push(['data' => [], 'meta' => ['completed_subjects' => []]])->push(['data' => [], 'meta' => ['completed_subjects' => [$id, $id]]])->push(['data' => [], 'meta' => ['completed_subjects' => ['unexpected']]])]);
        $result = app(ResolveNegativePnlPeriodsPort::class)->resolve(new ResolveNegativePnlPeriodsQueryData([new NegativePnlSubjectData($id)], '2026-10-02T10:00:00Z'));
        self::assertSame([], $result->periods);
        self::assertSame([$id], $result->completed_subjects);
        foreach ([[], [$id, $id], ['unexpected']] as $completed) {
            try {
                $this->resolveEmpty();
                self::fail('Incomplete or duplicate acknowledgement must be rejected.');
            } catch (InvalidNegativePnlPeriodsResponseException) {
                self::assertTrue(true);
            }
        }
    }

    private function resolveEmpty(): void
    {
        $this->app->make(ResolveNegativePnlPeriodsPort::class)->resolve(
            new ResolveNegativePnlPeriodsQueryData([new NegativePnlSubjectData('11111111-1111-4111-8111-111111111111')], '2026-10-02T10:00:00Z'),
        );
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/Rewards/broker-negative-pnl-periods-v1.json'));
        self::assertIsString($contents);

        $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture['data'] as &$row) {
            $row['balance_read_id'] = '123';
            $row['balance_read_at'] = '2026-10-02T09:58:00Z';
        }
        $fixture['meta']['completed_subjects'] = ['11111111-1111-4111-8111-111111111111'];

        return $fixture;
    }
}
