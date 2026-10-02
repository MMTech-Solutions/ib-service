<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\Factories\VolumeRewardProcessingRepositoryFactory;

final class RecordVolumeRewardEventUseCase
{
    public function __construct(private readonly VolumeRewardProcessingRepositoryFactory $repositoryFactory) {}

    public function execute(RecordVolumeRewardEventData $data): void
    {
        $this->repositoryFactory->make()->recordEvent($data);
    }
}
