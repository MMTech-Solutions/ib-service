<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Commands;

use App\Features\Rewards\DTOs\RewardReadAccessData;
use App\Features\Rewards\DTOs\RewardReadQueryData;
use App\Features\Rewards\Http\V1\Requests\ReadRewardsRequest;
use Spatie\LaravelData\Data;

final class ReadRewardsCommand extends Data
{
    public function __construct(public readonly RewardReadQueryData $query) {}

    public static function fromRequest(ReadRewardsRequest $request, RewardReadAccessData $access): self
    {
        $values = $request->validated();
        $page = (int) ($values['page'] ?? 1);
        $perPage = (int) ($values['per_page'] ?? 25);
        unset($values['page'], $values['per_page']);
        if ($access->beneficiary_id !== null) {
            $values['beneficiary_id'] = $access->beneficiary_id;
        }

        return new self(new RewardReadQueryData((string) $request->route('read_resource'), $request->route('reward') ?? $request->route('job') ?? $request->route('period'), $access->beneficiary_id, $access->include_audit, $values, $page, $perPage));
    }
}
