<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\DTOs\CpaVerificationProgressPageData;

interface CpaVerificationProgressRepositoryInterface
{
    public function paginate(CpaVerificationProgressListQueryData $query): CpaVerificationProgressPageData;
}
