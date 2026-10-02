<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\Contracts\Data\V1\RewardUplineBeneficiaryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use Carbon\CarbonImmutable;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineResponse;
use Throwable;

final class IamResolveRewardUplineAdapter implements ResolveRewardUplinePort
{
    public function __construct(private readonly ReferralNetworkServiceInterface $referralNetwork) {}

    public function resolve(ResolveRewardUplineQueryData $query): ResolveRewardUplineResultData
    {
        if ($query->max_distribution_level < 0) {
            return ResolveRewardUplineResultData::failed('invalid_max_distribution_level');
        }

        try {
            $result = $this->referralNetwork->getUpline($query->subject_external_user_id);
            if (! $result->isSuccess()) {
                return ResolveRewardUplineResultData::failed('unavailable');
            }
            $payload = $result->getMappedData(IbUplineResponse::class);
            if (! $payload instanceof IbUplineResponse) {
                return ResolveRewardUplineResultData::failed('invalid_response');
            }

            $beneficiaries = [];
            $keys = [];
            foreach ($payload->upline as $item) {
                $beneficiaryId = trim((string) $item->user->id);
                $level = $item->level - 1;
                $key = $beneficiaryId.'|'.$level;
                if ($beneficiaryId === '' || $level < 0 || isset($keys[$key])) {
                    return ResolveRewardUplineResultData::failed('invalid_response');
                }
                $keys[$key] = true;
                if ($level <= $query->max_distribution_level) {
                    $beneficiaries[] = new RewardUplineBeneficiaryData($beneficiaryId, $level);
                }
            }

            return ResolveRewardUplineResultData::resolved($beneficiaries, CarbonImmutable::now('UTC')->toIso8601String());
        } catch (Throwable) {
            return ResolveRewardUplineResultData::failed('invalid_response');
        }
    }
}
