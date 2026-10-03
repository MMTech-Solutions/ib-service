<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories;

use App\Features\Rewards\DTOs\NegativePnlJobReadData;
use App\Features\Rewards\DTOs\NegativePnlPeriodReadData;
use App\Features\Rewards\DTOs\RewardReadData;
use App\Features\Rewards\DTOs\RewardReadPageData;
use App\Features\Rewards\DTOs\RewardReadQueryData;

interface RewardReadRepositoryInterface
{
    public function paginate(RewardReadQueryData $query): RewardReadPageData;

    public function find(RewardReadQueryData $query): RewardReadData|NegativePnlJobReadData|NegativePnlPeriodReadData|null;
}
