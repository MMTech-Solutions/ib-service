<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Ports\Input\LockPlanRowsPort;

final class LockPlanRowsUseCase implements LockPlanRowsPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function lockAscending(array $planIds): void
    {
        $this->repositoryFactory->make()->lockAscending($planIds);
    }
}
