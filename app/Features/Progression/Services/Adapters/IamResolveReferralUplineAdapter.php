<?php

declare(strict_types=1);

namespace App\Features\Progression\Services\Adapters;

use App\Features\Progression\Contracts\Data\V1\ReferralUplineBeneficiaryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineResultData;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use Carbon\CarbonImmutable;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineResponse;
use Throwable;

final class IamResolveReferralUplineAdapter implements ResolveReferralUplinePort
{
    public function __construct(private readonly ReferralNetworkServiceInterface $referralNetwork) {}

    public function resolve(ResolveReferralUplineQueryData $query): ResolveReferralUplineResultData
    {
        try {
            $result = $this->referralNetwork->getUpline($query->source_external_user_id);
            if (! $result->isSuccess()) {
                return ResolveReferralUplineResultData::failed('unavailable');
            }

            $payload = $result->getMappedData(IbUplineResponse::class);
            if (! $payload instanceof IbUplineResponse) {
                return ResolveReferralUplineResultData::failed('invalid_response');
            }

            $beneficiaries = [];
            $keys = [];
            foreach ($payload->upline as $item) {
                $beneficiaryId = trim((string) $item->user->id);
                $level = $item->level - 1;
                $key = $beneficiaryId.'|'.$level;
                if ($beneficiaryId === '' || $level < 0 || isset($keys[$key])) {
                    return ResolveReferralUplineResultData::failed('invalid_response');
                }
                $keys[$key] = true;
                $beneficiaries[] = new ReferralUplineBeneficiaryData($beneficiaryId, $level);
            }

            return ResolveReferralUplineResultData::resolved($beneficiaries, CarbonImmutable::now('UTC')->toISOString());
        } catch (Throwable) {
            return ResolveReferralUplineResultData::failed('invalid_response');
        }
    }
}
