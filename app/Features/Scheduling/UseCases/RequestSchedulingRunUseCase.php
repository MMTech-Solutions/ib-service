<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\Services\AdmitSchedulingRunService;

final class RequestSchedulingRunUseCase
{
    public function __construct(private readonly AdmitSchedulingRunService $admission) {}

    public function execute(ManualRunData $input): RunData
    {
        return $this->admission->manual($input);
    }
}
