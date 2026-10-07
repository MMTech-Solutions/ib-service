<?php

declare(strict_types=1);

namespace App\Features\Settings\Contracts\Ports\Input;

use App\Features\Settings\Contracts\Data\V1\ResolvedSettingsData;

interface ResolveSettingsPort
{
    /** @param list<string> $keys */
    public function execute(array $keys): ResolvedSettingsData;
}
