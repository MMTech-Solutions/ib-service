<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionInvalidResponseException;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionNotReadyException;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionUnavailableException;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BrokerClosedPositionClientTest extends TestCase
{
    public function test_it_classifies_a_conflict_as_retryable_not_ready(): void
    {
        Http::fake(['*' => Http::response([], 409)]);

        $this->expectException(BrokerClosedPositionNotReadyException::class);
        app(BrokerInstrumentCatalogApiClient::class)->closedPosition('order-1', 'login-1');
    }

    public function test_it_classifies_server_and_transport_failures_as_unavailable(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        try {
            app(BrokerInstrumentCatalogApiClient::class)->closedPosition('order-1', 'login-1');
            self::fail('A 5xx response must be retryable.');
        } catch (BrokerClosedPositionUnavailableException) {
            self::assertTrue(true);
        }

        Http::fake(static fn (): never => throw new ConnectionException('timeout'));
        $this->expectException(BrokerClosedPositionUnavailableException::class);
        app(BrokerInstrumentCatalogApiClient::class)->closedPosition('order-1', 'login-1');
    }

    public function test_it_rejects_a_successful_response_with_an_invalid_contract(): void
    {
        Http::fake(['*' => Http::response(['data' => 'invalid'], 200)]);

        $this->expectException(BrokerClosedPositionInvalidResponseException::class);
        app(BrokerInstrumentCatalogApiClient::class)->closedPosition('order-1', 'login-1');
    }
}
