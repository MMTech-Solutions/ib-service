<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramVolumeRewardConfigurationRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;

final class ReplaceProgramVolumeRewardConfigurationUseCase
{
    public function __construct(private readonly ResolveProgramSubscriptionContextPort $programs, private readonly ProgramVolumeRewardConfigurationRepositoryFactory $repositoryFactory) {}

    /** @return array<string, mixed> */
    public function execute(string $planId, string $programId, string $mode): array
    {
        $this->programs->assertBelongsToPlan(new AssertProgramBelongsToPlanQueryData($planId, $programId));

        return $this->repositoryFactory->make()->replace($programId, $mode, now('UTC')->toIso8601String());
    }
}
