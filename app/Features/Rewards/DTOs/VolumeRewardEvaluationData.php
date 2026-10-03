<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use Spatie\LaravelData\Data;

final class VolumeRewardEvaluationData extends Data
{
    /** @param array<string, VolumeRewardPreparedInputsData> $preparations */
    public function __construct(public readonly string $id, public readonly string $lease_token, public readonly VolumeRewardActivityData $activity, public readonly ?ResolveRewardUplineResultData $distribution, public readonly array $preparations) {}
}
