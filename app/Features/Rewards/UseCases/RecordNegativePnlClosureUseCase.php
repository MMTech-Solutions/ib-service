<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\Contracts\Data\V1\RecordNegativePnlClosureData;
use App\Features\Rewards\Contracts\Ports\Input\RecordNegativePnlClosurePort;
use App\Features\Rewards\Factories\NegativePnlProcessingRepositoryFactory;

final class RecordNegativePnlClosureUseCase implements RecordNegativePnlClosurePort
{
    public function __construct(private readonly NegativePnlProcessingRepositoryFactory $repositoryFactory) {}

    public function execute(RecordNegativePnlClosureData $data): void
    {
        $this->repositoryFactory->make()->recordClosure($data);
    }
}
