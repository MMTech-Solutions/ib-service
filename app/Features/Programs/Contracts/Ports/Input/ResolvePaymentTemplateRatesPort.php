<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\NegativePnlPaymentLevelData;

interface ResolvePaymentTemplateRatesPort
{
    /** @return list<NegativePnlPaymentLevelData>|null */
    public function execute(string $versionId): ?array;
}
