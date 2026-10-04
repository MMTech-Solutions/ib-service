<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\PaymentTemplateVersionIdentityData;

interface ResolvePaymentTemplateVersionPort
{
    public function execute(string $versionId): ?PaymentTemplateVersionIdentityData;
}
