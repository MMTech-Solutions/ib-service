<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

interface ValidateModuleActivitySubscriptionsPort
{
    public function execute(): void;
}
