<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rewards\Exceptions\RewardSettlementException;
use App\Features\Rewards\Services\Adapters\FinanceRewardSettlementGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FinanceRewardRecoveryGatewayTest extends TestCase
{
    public function test_it_resolves_the_exact_wallet_on_a_later_page(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        config()->set('finance.internal_token', 'test-token');
        config()->set('finance.source_service', 'ib-service');
        Http::fake(function ($request) {
            self::assertSame('GET', $request->method());
            self::assertTrue($request->hasHeader('X-Internal-Token', 'test-token'));
            self::assertTrue($request->hasHeader('X-Internal-Source', 'ib-service'));
            if (str_contains($request->url(), '/commission-events?')) {
                self::assertSame('payment-key', $request['idempotency_key']);

                return Http::response(['data' => [$this->event()]]);
            }
            self::assertStringContainsString('/ib/wallets?', $request->url());
            self::assertSame('beneficiary', $request['ib_user_id']);
            self::assertSame(100, $request['per_page']);
            $page = $request['page'];
            self::assertLessThanOrEqual(2, $page);

            return Http::response(['data' => [$this->wallet($page === 1 ? 99 : 42)], 'meta' => ['current_page' => $page, 'last_page' => 3]]);
        });
        $event = app(FinanceRewardSettlementGateway::class)->findByIdempotencyKey('payment-key');
        self::assertSame('EUR', $event->currency_code);
        self::assertSame('eur-main', $event->system_wallet_slug);
        Http::assertSentCount(3);
    }

    public function test_absence_does_not_query_wallets(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        Http::preventStrayRequests();
        Http::fake(['http://finance.test/api/finance/v1/ib/commission-events*' => Http::response(['data' => []])]);
        self::assertNull(app(FinanceRewardSettlementGateway::class)->findByIdempotencyKey('payment-key'));
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_wallet_evidence_is_rejected(array $payload): void
    {
        $this->fakeRecovery($payload);
        $this->assertFailure('finance_contract_invalid');
        Http::assertSentCount(2);
    }

    public static function invalidResponses(): array
    {
        $wallet = ['id' => 42, 'ib_user_id' => 'beneficiary', 'minor_units' => 2, 'currency_code' => 'EUR', 'system_wallet_slug' => 'eur-main'];
        $meta = ['current_page' => 1, 'last_page' => 1];

        return [
            'absent' => [['data' => [], 'meta' => $meta]],
            'beneficiary' => [['data' => [[...$wallet, 'ib_user_id' => 'other']], 'meta' => $meta]],
            'precision' => [['data' => [[...$wallet, 'minor_units' => 3]], 'meta' => $meta]],
            'string id' => [['data' => [[...$wallet, 'id' => '42']], 'meta' => $meta]],
            'currency' => [['data' => [[...$wallet, 'currency_code' => null]], 'meta' => $meta]],
            'slug' => [['data' => [[...$wallet, 'system_wallet_slug' => '']], 'meta' => $meta]],
            'page' => [['data' => [$wallet], 'meta' => ['current_page' => 2, 'last_page' => 2]]],
            'last page' => [['data' => [$wallet], 'meta' => ['current_page' => 1, 'last_page' => '1']]],
            'missing meta' => [['data' => [$wallet]]],
            'collection' => [['data' => ['wallets' => [$wallet]], 'meta' => $meta]],
        ];
    }

    #[DataProvider('transportErrors')]
    public function test_wallet_transport_errors_never_return_absence(int $status, string $error): void
    {
        $this->fakeRecovery([], $status);
        $this->assertFailure($error);
        Http::assertSentCount(2);
    }

    public static function transportErrors(): array
    {
        return [[503, 'finance_unavailable'], [403, 'finance_rejected']];
    }

    public function test_wallet_connection_failure_is_unavailable(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        Http::fake([
            'http://finance.test/api/finance/v1/ib/commission-events*' => Http::response(['data' => [$this->event()]]),
            'http://finance.test/api/finance/v1/ib/wallets*' => Http::failedConnection(),
        ]);
        $this->assertFailure('finance_unavailable');
    }

    #[DataProvider('invalidEvents')]
    public function test_invalid_event_identity_is_rejected_before_wallet_lookup(array $change): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        Http::preventStrayRequests();
        Http::fake(['http://finance.test/api/finance/v1/ib/commission-events*' => Http::response(['data' => [[...$this->event(), ...$change]]])]);
        $this->assertFailure('finance_contract_invalid');
        Http::assertSentCount(1);
    }

    public static function invalidEvents(): array
    {
        return [[['ib_wallet_id' => null]], [['ib_wallet_id' => '42']], [['ib_wallet_id' => 0]], [['idempotency_key' => 'foreign-key']]];
    }

    public function test_incoherent_pagination_cannot_extend_the_lookup(): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        Http::fake(function ($request) {
            self::assertSame('GET', $request->method());
            if (str_contains($request->url(), '/commission-events?')) {
                return Http::response(['data' => [$this->event()]]);
            }
            $page = $request['page'];
            self::assertLessThanOrEqual(2, $page);

            return Http::response(['data' => [$this->wallet(99)], 'meta' => ['current_page' => $page, 'last_page' => $page + 1]]);
        });
        $this->assertFailure('finance_contract_invalid');
        Http::assertSentCount(3);
    }

    private function assertFailure(string $error): void
    {
        try {
            app(FinanceRewardSettlementGateway::class)->findByIdempotencyKey('payment-key');
            self::fail('Expected recovery failure.');
        } catch (RewardSettlementException $exception) {
            self::assertSame($error, $exception->error_code);
        }
    }

    private function fakeRecovery(array $payload, int $status = 200): void
    {
        config()->set('finance.base_url', 'http://finance.test');
        Http::preventStrayRequests();
        Http::fake([
            'http://finance.test/api/finance/v1/ib/commission-events*' => Http::response(['data' => [$this->event()]]),
            'http://finance.test/api/finance/v1/ib/wallets*' => Http::response($payload, $status),
        ]);
    }

    private function event(): array
    {
        return ['id' => 781, 'idempotency_key' => 'payment-key', 'commission_type' => 'cpa', 'ib_user_id' => 'beneficiary', 'ib_wallet_id' => 42, 'amount_minor' => 2500, 'minor_units' => 2, 'reference_type' => 'reward', 'reference_id' => 'reward-id', 'status' => 'posted', 'network_level' => 1];
    }

    private function wallet(int $id): array
    {
        return ['id' => $id, 'ib_user_id' => 'beneficiary', 'minor_units' => 2, 'currency_code' => 'EUR', 'system_wallet_slug' => 'eur-main'];
    }
}
