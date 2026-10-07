<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Sources\Contracts\VolumeRewardActivityNormalizerInterface;
use App\Features\Modules\Sources\Support\VolumeRewardActivityValidator;

final class CopyTradingVolumeRewardActivityNormalizer implements VolumeRewardActivityNormalizerInterface
{
    public function __construct(private readonly VolumeRewardActivityValidator $validator) {}

    /** @param array<string, mixed> $activity */
    public function normalize(string $moduleId, array $activity): VolumeRewardActivityData
    {
        return $this->validator->normalize('copy_trading', $moduleId, $activity);
    }
}
