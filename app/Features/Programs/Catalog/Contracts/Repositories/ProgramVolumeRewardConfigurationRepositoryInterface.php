<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Contracts\Repositories;

interface ProgramVolumeRewardConfigurationRepositoryInterface
{
    /** @return array<string, mixed> */
    public function replace(string $programId, string $mode, string $at): array;
}
