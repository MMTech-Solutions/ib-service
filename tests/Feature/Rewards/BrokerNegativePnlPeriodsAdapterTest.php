<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlSubjectData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BrokerNegativePnlPeriodsAdapterTest extends TestCase
{
    public function test_signed_profit_and_all_position_ids_are_mapped_without_baselines(): void
    {
        Http::fake(['*' => Http::response($this->fixture())]);
        $result = app(ResolveNegativePnlPeriodsPort::class)->resolve($this->activityQuery());
        self::assertSame('125.5000000000', $result->periods[0]->npnl);
        self::assertSame('-40.0000000000', $result->periods[1]->npnl);
        self::assertSame(['position-loss', 'position-gain'], $result->periods[1]->position_ids);
        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('X-Internal-Source', 'mmt-ib-service')
            && $request['occurred_from'] === '2026-10-01T10:00:00Z'
            && ! isset($request['subjects'][0]['baselines']));
    }

    public function test_invalid_ownership_intervals_duplicates_and_decimal_types_are_rejected(): void
    {
        foreach (['foreign_user', 'duplicate_account', 'duplicate_position', 'bad_interval', 'numeric_profit', 'empty_evidence', 'missing_completed', 'duplicate_completed'] as $case) {
            $fixture = $this->fixture();
            switch ($case) {
                case 'foreign_user': $fixture['data'][0]['external_user_id'] = 'foreign';
                    break;
                case 'duplicate_account': $fixture['data'][] = $fixture['data'][0];
                    break;
                case 'duplicate_position': $fixture['data'][1]['position_ids'][] = 'position-positive';
                    break;
                case 'bad_interval': $fixture['data'][0]['occurred_from'] = '2026-09-01';
                    break;
                case 'numeric_profit': $fixture['data'][0]['npnl'] = 125.5;
                    break;
                case 'empty_evidence': $fixture['data'][0]['position_ids'] = [];
                    break;
                case 'missing_completed': $fixture['meta']['completed_subjects'] = [];
                    break;
                case 'duplicate_completed': $fixture['meta']['completed_subjects'][] = $fixture['meta']['completed_subjects'][0];
                    break;
            }
            Http::swap(new Factory);
            Http::fake(['*' => Http::response($fixture)]);
            try {
                app(ResolveNegativePnlPeriodsPort::class)->resolve($this->activityQuery());
                self::fail($case);
            } catch (InvalidNegativePnlPeriodsResponseException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_empty_completed_user_is_valid(): void
    {
        $fixture = $this->fixture();
        $fixture['data'] = [];
        Http::fake(['*' => Http::response($fixture)]);
        self::assertSame([], app(ResolveNegativePnlPeriodsPort::class)->resolve($this->activityQuery())->periods);
    }

    public function test_transport_errors_remain_recoverable_and_other_rejections_invalid(): void
    {
        foreach ([409, 500, 422, 403] as $status) {
            Http::swap(new Factory);
            Http::fake(['*' => Http::response([], $status)]);
            try {
                app(ResolveNegativePnlPeriodsPort::class)->resolve($this->activityQuery());
                self::fail('Expected rejection');
            } catch (NegativePnlPeriodsUnavailableException|InvalidNegativePnlPeriodsResponseException $error) {
                self::assertInstanceOf(in_array($status, [409, 500], true) ? NegativePnlPeriodsUnavailableException::class : InvalidNegativePnlPeriodsResponseException::class, $error);
            }
        }
    }

    private function activityQuery(): ResolveNegativePnlPeriodsQueryData
    {
        return new ResolveNegativePnlPeriodsQueryData([new NegativePnlSubjectData('11111111-1111-4111-8111-111111111111')], '2026-10-02T10:00:00Z', '2026-10-01T10:00:00Z');
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/Rewards/broker-negative-pnl-periods-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
