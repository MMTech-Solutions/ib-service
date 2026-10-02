<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramVolumeRewardConfigurationRepositoryFactory;
use App\Features\Programs\Catalog\Http\V1\Commands\ReplaceProgramVolumeRewardConfigurationCommand;
use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;

final class ReplaceProgramVolumeRewardConfigurationUseCase
{
    public function __construct(private readonly ResolveProgramSubscriptionContextPort $programs, private readonly ProgramVolumeRewardConfigurationRepositoryFactory $repositoryFactory) {}

    /** @return array<string, mixed> */
    public function execute(ReplaceProgramVolumeRewardConfigurationCommand $command): array
    {
        $this->programs->assertBelongsToPlan(new AssertProgramBelongsToPlanQueryData($command->planId, $command->programId));

        return $this->repositoryFactory->make()->replace($command->programId, $command->mode, now('UTC')->toIso8601String());
    }
}
