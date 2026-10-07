<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Contracts;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;

interface VolumeRewardActivityNormalizerInterface
{
    /** @param array<string, mixed> $activity */
    public function normalize(string $moduleId, array $activity): VolumeRewardActivityData;
}
