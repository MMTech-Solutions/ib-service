<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Services;

use App\Features\Modules\Contracts\Data\V1\ConnectionCertificationData;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class CertifyProviderConnectionService
{
    public function __construct(private readonly ProviderConnectionRegistry $providers, private readonly DomainClock $clock) {}

    public function certify(string $provider, ?string $moduleId = null): ConnectionCertificationData
    {
        $started = hrtime(true);
        $checkedAt = $this->clock->now()->toISOString();
        $connection = $this->providers->resolve($provider);
        if ($connection->base_url === '' || $connection->internal_token === null || $connection->internal_token === '' || $connection->source_service === '') {
            $code = 'not_configured';
        } else {
            try {
                $response = Http::baseUrl(rtrim($connection->base_url, '/'))->acceptJson()
                    ->timeout($connection->timeout_seconds)->connectTimeout(min(3, $connection->timeout_seconds))
                    ->withoutRedirecting()->withHeaders([
                        'X-Internal-Token' => $connection->internal_token,
                        'X-Internal-Source' => $connection->source_service,
                    ])->get(rtrim($connection->internal_prefix, '/').'/health');
                $code = match ($response->status()) {
                    401 => 'authentication_rejected',
                    403 => 'authorization_rejected',
                    404 => 'endpoint_missing',
                    503 => 'unhealthy',
                    200 => $response->json('success') === true
                        && $response->json('data.status') === 'ready'
                        && $response->json('data.service') === $connection->expected_service
                        && $response->json('data.schema_version') === 1 ? 'certified' : 'invalid_response',
                    default => $response->serverError() ? 'unhealthy' : 'invalid_response',
                };
            } catch (ConnectionException $error) {
                $code = str_contains($error->getMessage(), 'cURL error 28') ? 'timeout' : 'unreachable';
            }
        }

        return new ConnectionCertificationData($provider, $moduleId, $code === 'certified', $code, $checkedAt, (int) ((hrtime(true) - $started) / 1000000));
    }
}
