<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Contracts\Repositories;

use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingPageData;
use App\Features\Plans\Catalog\DTOs\StorePaymentTemplateBindingResultData;

interface PaymentTemplateBindingRepositoryInterface
{
    public function resolve(string $planId, string $bindingId): ?string;

    public function find(string $planId, string $bindingId): ?PaymentTemplateBindingData;

    public function paginate(string $planId, int $page, int $perPage): PaymentTemplateBindingPageData;

    public function createOrFind(PaymentTemplateBindingData $binding): StorePaymentTemplateBindingResultData;
}
