<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\VolumeRewardActivityNormalizerFactory;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Ports\Input\ValidateModuleActivitySubscriptionsPort;
use InvalidArgumentException;

final class ValidateModuleActivitySubscriptionsUseCase implements ValidateModuleActivitySubscriptionsPort
{
    public function __construct(
        private readonly ModuleDefinitionRegistry $registry,
        private readonly VolumeRewardActivityNormalizerFactory $normalizers,
    ) {}

    public function execute(): void
    {
        $routes = [];
        foreach ($this->registry->definitions() as $definition) {
            foreach ($definition->capabilities as $capability) {
                if ($capability->code !== 'closed_trading_volume') {
                    continue;
                }
                foreach ($capability->event_subscriptions as $subscription) {
                    if ($subscription->module_code !== $definition->code
                        || ! preg_match('/^[a-zA-Z0-9._-]{1,249}$/D', $subscription->topic)
                        || in_array($subscription->topic, ['.', '..'], true)
                        || trim($subscription->event_name) === ''
                        || trim($subscription->event_name) !== $subscription->event_name
                        || $subscription->schema_version < 1) {
                        throw new InvalidArgumentException('Invalid module activity subscription configuration.');
                    }
                    $key = json_encode([$subscription->topic, $subscription->event_name, $subscription->schema_version], JSON_THROW_ON_ERROR);
                    if (isset($routes[$key])) {
                        throw new InvalidArgumentException('Ambiguous module activity subscription.');
                    }
                    $routes[$key] = true;
                    $this->normalizers->make($subscription->adapter);
                }
            }
        }
    }
}
