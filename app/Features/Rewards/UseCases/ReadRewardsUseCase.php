<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\NegativePnlJobReadData;
use App\Features\Rewards\DTOs\NegativePnlPeriodReadData;
use App\Features\Rewards\DTOs\RewardReadData;
use App\Features\Rewards\DTOs\RewardReadPageData;
use App\Features\Rewards\DTOs\RewardReadQueryData;
use App\Features\Rewards\Exceptions\RewardNotFoundException;
use App\Features\Rewards\Factories\RewardReadRepositoryFactory;

final class ReadRewardsUseCase
{
    public function __construct(private readonly RewardReadRepositoryFactory $repositories) {}

    public function execute(RewardReadQueryData $query): RewardReadData|NegativePnlJobReadData|NegativePnlPeriodReadData|RewardReadPageData
    {
        $repository = $this->repositories->make();
        if ($query->id === null) {
            return $repository->paginate($query);
        }

        return $repository->find($query) ?? throw RewardNotFoundException::forId($query->id);
    }
}
