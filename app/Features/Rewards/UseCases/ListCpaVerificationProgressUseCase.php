<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\DTOs\CpaVerificationProgressPageData;
use App\Features\Rewards\Factories\CpaVerificationProgressRepositoryFactory;

final class ListCpaVerificationProgressUseCase
{
    public function __construct(private readonly CpaVerificationProgressRepositoryFactory $repositoryFactory) {}

    public function execute(CpaVerificationProgressListQueryData $query): CpaVerificationProgressPageData
    {
        return $this->repositoryFactory->make()->paginate($query);
    }
}
