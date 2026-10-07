<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Factories\VolumeRewardActivityNormalizerFactory;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use Illuminate\Support\Str;

final class NormalizeVolumeRewardEventUseCase implements NormalizeVolumeRewardEventPort
{
    public function __construct(
        private readonly ModuleDefinitionRegistry $registry,
        private readonly ModuleRepositoryFactory $repositories,
        private readonly VolumeRewardActivityNormalizerFactory $normalizers,
    ) {}

    public function execute(VolumeRewardEventQueryData $query): VolumeRewardEventData
    {
        $body = $query->body;
        if (! is_string($body['event_id'] ?? null) || ! Str::isUuid($body['event_id']) || ($body['schema_version'] ?? null) !== 1 || ! is_array($body['activity'] ?? null)) {
            throw InvalidVolumeRewardActivityException::create();
        }
        foreach ($this->registry->definitions() as $definition) {
            foreach ($definition->capabilities as $capability) {
                if ($capability->code !== 'closed_trading_volume') {
                    continue;
                }
                foreach ($capability->event_subscriptions as $subscription) {
                    if ($subscription->topic !== $query->topic || $subscription->event_name !== $query->event_name || $subscription->schema_version !== $body['schema_version']) {
                        continue;
                    }
                    if (($body['provider_code'] ?? null) !== $definition->code) {
                        throw InvalidVolumeRewardActivityException::create();
                    }
                    $module = $this->repositories->make()->findByCode($definition->code);
                    if ($module === null || ! array_filter($module->capabilities, static fn ($stored): bool => $stored->code === $capability->code)) {
                        throw InvalidVolumeRewardActivityException::create();
                    }

                    return new VolumeRewardEventData($body['event_id'], $body['schema_version'], $this->normalizers->make($subscription->adapter)->normalize($module->id, $body['activity']));
                }
            }
        }
        throw InvalidVolumeRewardActivityException::create();
    }
}
