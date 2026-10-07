<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services;

use App\Features\Modules\Contracts\Exceptions\ActivityProviderUnavailableException;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CopyTradingApiClient
{
    /** @param array<string, mixed> $query @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function get(string $path, array $query): array
    {
        $baseUrl = (string) config('modules.sources.copy_trading.base_url');
        if ($baseUrl === '') {
            throw ActivityProviderUnavailableException::create();
        }
        try {
            $response = Http::baseUrl($baseUrl)->acceptJson()->timeout((int) config('modules.sources.copy_trading.timeout_seconds', 15))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) config('modules.sources.copy_trading.internal_token'),
                    'X-Internal-Source' => (string) config('modules.sources.copy_trading.source_service'),
                ])->get((string) config('modules.sources.copy_trading.internal_prefix').$path, $query);
        } catch (Throwable) {
            throw ActivityProviderUnavailableException::create();
        }
        if (! $response->successful()) {
            throw ActivityProviderUnavailableException::create();
        }
        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) !== true || ! is_array($body['data'] ?? null) || ! array_is_list($body['data']) || ! is_array($body['meta'] ?? null)) {
            throw InvalidVolumeRewardActivityException::create();
        }

        return ['data' => $body['data'], 'meta' => $body['meta']];
    }
}
