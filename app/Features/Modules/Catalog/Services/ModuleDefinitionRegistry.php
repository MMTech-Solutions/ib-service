<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Services;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;

final class ModuleDefinitionRegistry
{
    /**
     * @param  list<ModuleDefinitionData>|null  $registeredDefinitions
     */
    public function __construct(private readonly ?array $registeredDefinitions = null) {}

    /** @return list<ModuleDefinitionData> */
    public function definitions(): array
    {
        return $this->registeredDefinitions ?? [
            new ModuleDefinitionData(
                code: 'broker',
                name: 'Broker',
                description: 'Broker-related activity available to IB programs.',
                capabilities: [
                    new ModuleCapabilityDefinitionData(
                        code: 'deposits',
                        name: 'Deposits',
                        description: 'Confirmed deposits attributable to Broker activity.',
                    ),
                    new ModuleCapabilityDefinitionData(
                        code: 'closed_trading_volume',
                        name: 'Closed trading volume',
                        description: 'Closed trading volume attributable to Broker activity.',
                        event_subscriptions: [new ModuleActivitySubscriptionData('broker', (string) config('modules.sources.broker.topic'), 'position_closed', 1, 'broker_closed_volume_v1')],
                    ),
                ],
            ),
            new ModuleDefinitionData(
                code: 'copy_trading',
                name: 'Copy Trading',
                description: 'Copy Trading closed volume activity.',
                capabilities: [
                    new ModuleCapabilityDefinitionData(
                        code: 'closed_trading_volume',
                        name: 'Closed trading volume',
                        description: 'Closed trading volume attributable to Copy Trading.',
                        event_subscriptions: [new ModuleActivitySubscriptionData('copy_trading', (string) config('modules.sources.copy_trading.topic'), 'position_closed', 1, 'copy_trading_closed_volume_v1')],
                    ),
                ],
            ),
        ];
    }
}
