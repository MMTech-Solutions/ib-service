<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\Repositories\PostgreSql\PostgreSqlVolumeRewardProcessingRepository;

final class RecordVolumeRewardEventUseCase
{
    public function __construct(private readonly PostgreSqlVolumeRewardProcessingRepository $repository) {}

    public function execute(RecordVolumeRewardEventData $data): void
    {
        $this->repository->recordEvent($data);
    }
}
