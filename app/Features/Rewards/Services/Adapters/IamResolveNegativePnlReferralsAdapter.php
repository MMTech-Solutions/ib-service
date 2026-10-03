<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlReferralData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlReferralsPort;
use App\Features\Rewards\Exceptions\InvalidNegativePnlReferralsException;
use App\Features\Rewards\Exceptions\NegativePnlReferralsUnavailableException;
use Illuminate\Support\Str;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbDownlineLevelItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbDownlineResponse;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbReferralUserItem;
use Throwable;

final class IamResolveNegativePnlReferralsAdapter implements ResolveNegativePnlReferralsPort
{
    public function __construct(private readonly ReferralNetworkServiceInterface $network) {}

    public function resolve(string $beneficiaryId, int $maxDistributionLevel): array
    {
        if ($maxDistributionLevel < 0) {
            throw InvalidNegativePnlReferralsException::create();
        }
        try {
            $response = $this->network->getDownline($beneficiaryId, $maxDistributionLevel + 1);
        } catch (Throwable) {
            throw NegativePnlReferralsUnavailableException::create();
        }
        if (! $response->isSuccess()) {
            throw NegativePnlReferralsUnavailableException::create();
        }
        try {
            $data = $response->getMappedData(IbDownlineResponse::class);
        } catch (Throwable) {
            throw InvalidNegativePnlReferralsException::create();
        }
        if (! $data instanceof IbDownlineResponse) {
            throw InvalidNegativePnlReferralsException::create();
        }
        $items = [];
        $seen = [];
        $levels = [];
        foreach ($data->downline as $level) {
            if (! $level instanceof IbDownlineLevelItem) {
                throw InvalidNegativePnlReferralsException::create();
            }
            $index = $level->level - 1;
            if ($index < 0 || isset($levels[$index])) {
                throw InvalidNegativePnlReferralsException::create();
            }
            $levels[$index] = true;
            foreach ($level->users as $user) {
                if (! $user instanceof IbReferralUserItem) {
                    throw InvalidNegativePnlReferralsException::create();
                }
                $id = strtolower(trim($user->id));
                if (! Str::isUuid($id) || $id === strtolower($beneficiaryId) || isset($seen[$id])) {
                    throw InvalidNegativePnlReferralsException::create();
                }
                $seen[$id] = true;
                if ($index <= $maxDistributionLevel) {
                    $items[] = new NegativePnlReferralData($id, $index);
                }
            }
        }

        return $items;
    }
}
