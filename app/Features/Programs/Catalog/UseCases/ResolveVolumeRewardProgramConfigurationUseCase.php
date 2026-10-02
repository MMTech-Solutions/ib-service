<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramVolumeRewardConfigurationRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardProgramConfigurationData;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardProgramConfigurationPort;

final class ResolveVolumeRewardProgramConfigurationUseCase implements ResolveVolumeRewardProgramConfigurationPort
{
    public function __construct(private readonly ProgramVolumeRewardConfigurationRepositoryFactory $repositoryFactory) {}

    public function execute(ResolveVolumeRewardProgramConfigurationQueryData $query): ?VolumeRewardProgramConfigurationData
    {
        return $this->repositoryFactory->make()->resolveProgramConfiguration($query);
    }
}
