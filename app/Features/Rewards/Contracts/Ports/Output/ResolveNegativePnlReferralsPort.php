<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Output;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlReferralData;

interface ResolveNegativePnlReferralsPort
{
    /** @return list<NegativePnlReferralData> */
    public function resolve(string $beneficiaryId, int $maxDistributionLevel): array;
}
