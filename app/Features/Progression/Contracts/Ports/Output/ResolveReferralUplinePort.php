<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Ports\Output;

use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineResultData;

interface ResolveReferralUplinePort
{
    public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData;
}
