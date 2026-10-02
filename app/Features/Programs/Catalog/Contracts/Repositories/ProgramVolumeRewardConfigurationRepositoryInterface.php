<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Contracts\Repositories;

use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardDistributionLimitQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardDistributionLimitData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardProgramConfigurationData;

interface ProgramVolumeRewardConfigurationRepositoryInterface
{
    /** @return array<string, mixed> */
    public function replace(string $programId, string $mode, string $at): array;

    public function resolveDistributionLimit(ResolveVolumeRewardDistributionLimitQueryData $query): ?VolumeRewardDistributionLimitData;

    public function resolveProgramConfiguration(ResolveVolumeRewardProgramConfigurationQueryData $query): ?VolumeRewardProgramConfigurationData;
}
