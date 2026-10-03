<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Contracts\Repositories;

interface PaymentTemplateBindingRepositoryInterface
{
    public function resolve(string $planId, string $bindingId): ?string;
}
