<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\ManageRewardFinancialOperationData;
use App\Features\Rewards\DTOs\RewardFinancialOperationData;
use App\Features\Rewards\Services\RewardFinancialOperationService;

final class ManageRewardFinancialOperationUseCase
{
    public function __construct(private readonly RewardFinancialOperationService $operations) {}

    public function execute(ManageRewardFinancialOperationData $data): RewardFinancialOperationData
    {
        return $this->operations->execute($data);
    }
}
