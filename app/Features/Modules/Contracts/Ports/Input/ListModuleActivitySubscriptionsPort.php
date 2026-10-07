<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;

interface ListModuleActivitySubscriptionsPort
{
    /** @return list<ModuleActivitySubscriptionData> */
    public function execute(): array;
}
