<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;
use Spatie\LaravelData\Data;

final class ModuleCapabilityDefinitionData extends Data
{
    /** @param list<ModuleActivitySubscriptionData> $event_subscriptions */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $event_subscriptions = [],
    ) {}
}
