<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

use App\Features\Plans\Contracts\Data\V1\ResolvePaymentTemplateBindingQueryData;

interface ResolvePaymentTemplateBindingPort
{
    public function execute(ResolvePaymentTemplateBindingQueryData $query): ?string;
}
