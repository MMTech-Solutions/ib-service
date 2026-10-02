<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Contracts\Repositories\CpaVerificationProgressRepositoryInterface;
use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\DTOs\CpaVerificationProgressPageData;

final class ListCpaVerificationProgressUseCase
{
    public function __construct(private readonly CpaVerificationProgressRepositoryInterface $repository) {}

    public function execute(CpaVerificationProgressListQueryData $query): CpaVerificationProgressPageData
    {
        return $this->repository->paginate($query);
    }
}
