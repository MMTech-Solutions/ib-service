<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Rewards\DTOs\CaptureNegativePnlCutData;
use App\Features\Rewards\DTOs\NegativePnlCutSnapshotData;
use App\Features\Rewards\Services\CaptureNegativePnlCutService;

final class CaptureNegativePnlCutUseCase
{
    public function __construct(private readonly CaptureNegativePnlCutService $service) {}

    public function execute(CaptureNegativePnlCutData $data): NegativePnlCutSnapshotData
    {
        return $this->service->execute($data);
    }
}
