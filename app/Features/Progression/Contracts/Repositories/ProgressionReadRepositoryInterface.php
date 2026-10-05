<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Repositories;

use App\Features\Progression\DTOs\ProgressionReadPageData;
use App\Features\Progression\DTOs\ProgressionReadQueryData;

interface ProgressionReadRepositoryInterface
{
    public function read(ProgressionReadQueryData $query): ProgressionReadPageData;
}
