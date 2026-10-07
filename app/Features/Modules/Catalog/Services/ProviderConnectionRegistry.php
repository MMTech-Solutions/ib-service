<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Services;

use App\Features\Modules\Catalog\DTOs\ProviderConnectionData;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Support\Exceptions\ApiException;

final class ProviderConnectionRegistry
{
    public function __construct(private readonly ResolveSettingsPort $settings) {}

    public function resolve(string $provider): ProviderConnectionData
    {
        $service = match ($provider) {
            'broker' => 'broker-service',
            'copy_trading' => 'copy-trading-service',
            default => throw new ApiException('PROVIDER_NOT_FOUND', 'The activity provider is not registered.', 404),
        };
        $prefix = "modules.sources.{$provider}.";
        $settings = $this->settings->execute(array_map(static fn (string $key): string => $prefix.$key, ['base_url', 'internal_prefix', 'internal_token', 'source_service', 'timeout_seconds']));

        return new ProviderConnectionData($provider, $service, $settings->get($prefix.'base_url'), $settings->get($prefix.'internal_prefix'), $settings->get($prefix.'internal_token'), $settings->get($prefix.'source_service'), $settings->get($prefix.'timeout_seconds'));
    }
}
