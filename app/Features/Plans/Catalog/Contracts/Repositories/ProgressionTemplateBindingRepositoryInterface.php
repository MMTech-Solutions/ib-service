<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Contracts\Repositories;

use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingPageData;
use App\Features\Plans\Catalog\DTOs\StoreProgressionTemplateBindingResultData;

interface ProgressionTemplateBindingRepositoryInterface
{
    public function resolve(string $planId, string $bindingId): ?string;

    public function find(string $planId, string $bindingId): ?ProgressionTemplateBindingData;

    public function paginate(string $planId, int $page, int $perPage): ProgressionTemplateBindingPageData;

    public function createOrFind(ProgressionTemplateBindingData $binding): StoreProgressionTemplateBindingResultData;
}
