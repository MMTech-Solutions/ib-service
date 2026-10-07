<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;
use App\Features\Modules\Contracts\Ports\Input\ListModuleActivitySubscriptionsPort;

final class ListModuleActivitySubscriptionsUseCase implements ListModuleActivitySubscriptionsPort
{
    public function __construct(private readonly ModuleDefinitionRegistry $registry) {}

    /** @return list<ModuleActivitySubscriptionData> */
    public function execute(): array
    {
        $subscriptions = [];
        foreach ($this->registry->definitions() as $definition) {
            foreach ($definition->capabilities as $capability) {
                if ($capability->code === 'closed_trading_volume') {
                    array_push($subscriptions, ...$capability->event_subscriptions);
                }
            }
        }

        return $subscriptions;
    }
}
