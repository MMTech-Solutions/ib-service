<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services;

use App\Features\Modules\Contracts\Exceptions\FinanceCertifiedDepositsUnavailableException;
use Illuminate\Support\Facades\Http;

final class FinanceCertifiedDepositsApiClient
{
    /** @return list<array<string, mixed>> */
    public function list(string $externalUserId, string $from, string $until, string $currencyCode): array
    {
        $cursor = null;
        $items = [];
        do {
            try {
                $response = Http::baseUrl((string) config('finance.base_url'))->acceptJson()->timeout((int) config('finance.timeout_seconds'))->connectTimeout(3)->withHeaders([
                    'X-Internal-Token' => (string) config('finance.internal_token'),
                    'X-Internal-Source' => (string) config('finance.source_service'),
                ])->get('/api/finance/v1/users/'.$externalUserId.'/settled-external-deposits', array_filter([
                    'from' => $from, 'until' => $until, 'limit' => 100, 'cursor' => $cursor,
                ]));
            } catch (\Throwable) {
                throw FinanceCertifiedDepositsUnavailableException::create();
            }
            $body = $response->json();
            if (! $response->successful() || ! is_array($body) || ! is_array($body['data'] ?? null)) {
                throw FinanceCertifiedDepositsUnavailableException::create();
            }
            foreach ($body['data'] as $item) {
                if (is_array($item) && strtoupper((string) ($item['currency_code'] ?? '')) === strtoupper($currencyCode)) {
                    $items[] = $item;
                }
            }
            $next = $body['meta']['next_cursor'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
        } while ($cursor !== null);

        return $items;
    }
}
