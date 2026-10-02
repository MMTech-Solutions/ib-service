<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\ProgramVolumeRewardConfigurationRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardDistributionLimitQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardDistributionLimitData;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardDistributionLimitPort;

final class ResolveVolumeRewardDistributionLimitUseCase implements ResolveVolumeRewardDistributionLimitPort
{
    public function __construct(private readonly ProgramVolumeRewardConfigurationRepositoryFactory $repositoryFactory) {}

    public function execute(ResolveVolumeRewardDistributionLimitQueryData $query): ?VolumeRewardDistributionLimitData
    {
        return $this->repositoryFactory->make()->resolveDistributionLimit($query);
    }
}
