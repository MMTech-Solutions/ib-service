<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineQueryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use App\Features\Rewards\Services\Adapters\IamResolveRewardUplineAdapter;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbReferralUserItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineLevelItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineResponse;
use Mmt\IamServiceSdk\TransportDrivers\Contracts\ActionResultInterface;
use Mockery;
use Tests\TestCase;

final class RewardUplineAdapterTest extends TestCase
{
    public function test_it_cuts_the_iam_upline_to_the_maximum_payable_level(): void
    {
        $response = Mockery::mock(ActionResultInterface::class);
        $response->shouldReceive('isSuccess')->once()->andReturnTrue();
        $response->shouldReceive('getMappedData')->once()->andReturn(new IbUplineResponse(3, [
            new IbUplineLevelItem(1, new IbReferralUserItem('direct')),
            new IbUplineLevelItem(2, new IbReferralUserItem('parent')),
            new IbUplineLevelItem(3, new IbReferralUserItem('root')),
        ]));
        $service = Mockery::mock(ReferralNetworkServiceInterface::class);
        $service->shouldReceive('getUpline')->once()->with('referred')->andReturn($response);

        $result = (new IamResolveRewardUplineAdapter($service))->resolve(new ResolveRewardUplineQueryData('referred', 1));

        self::assertTrue($result->isResolved());
        self::assertSame(['direct', 'parent'], array_map(fn ($item): string => $item->beneficiary_external_user_id, $result->beneficiaries));
        self::assertSame([0, 1], array_map(fn ($item): int => $item->distribution_level, $result->beneficiaries));
    }

    public function test_the_provider_registers_a_dedicated_rewards_port(): void
    {
        self::assertInstanceOf(ResolveRewardUplinePort::class, $this->app->make(ResolveRewardUplinePort::class));
    }
}
