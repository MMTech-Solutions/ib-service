<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\CertifiedDepositEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCertifiedDepositsQueryData;

interface ListCertifiedDepositsPort
{
    /** @return list<CertifiedDepositEvidenceData> */
    public function execute(ListCertifiedDepositsQueryData $query): array;
}
