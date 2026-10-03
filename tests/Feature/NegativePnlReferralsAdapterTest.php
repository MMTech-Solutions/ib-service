<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Rewards\Exceptions\InvalidNegativePnlReferralsException;
use App\Features\Rewards\Exceptions\NegativePnlReferralsUnavailableException;
use App\Features\Rewards\Services\Adapters\IamResolveNegativePnlReferralsAdapter;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbDownlineLevelItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbDownlineResponse;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbReferralUserItem;
use Mmt\IamServiceSdk\TransportDrivers\Contracts\ActionResultInterface;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NegativePnlReferralsAdapterTest extends TestCase
{
    public function test_requested_depth_normalization_and_remunerable_filter(): void
    {
        $adapter = $this->adapter(new IbDownlineResponse(2, [new IbDownlineLevelItem(1, [new IbReferralUserItem('00000000-0000-4000-8000-000000000001')]), new IbDownlineLevelItem(2, [new IbReferralUserItem('00000000-0000-4000-8000-000000000002')]), new IbDownlineLevelItem(3, [new IbReferralUserItem('00000000-0000-4000-8000-000000000003')])]));
        $rows = $adapter->resolve('00000000-0000-4000-8000-000000000009', 1);
        self::assertSame(['00000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000002'], array_column($rows, 'external_user_id'));
        self::assertSame([0, 1], array_column($rows, 'distribution_level'));
    }

    #[DataProvider('invalidNetworks')]
    public function test_incompatible_networks_are_rejected(array $levels): void
    {
        $adapter = $this->adapter(new IbDownlineResponse(2, $levels));
        $this->expectException(InvalidNegativePnlReferralsException::class);
        $adapter->resolve('00000000-0000-4000-8000-000000000009', 1);
    }

    public static function invalidNetworks(): array
    {
        return [
            'self' => [[new IbDownlineLevelItem(1, [new IbReferralUserItem('00000000-0000-4000-8000-000000000009')])]],
            'blank' => [[new IbDownlineLevelItem(1, [new IbReferralUserItem(' ')])]],
            'duplicate user' => [[new IbDownlineLevelItem(1, [new IbReferralUserItem('00000000-0000-4000-8000-000000000001')]), new IbDownlineLevelItem(2, [new IbReferralUserItem('00000000-0000-4000-8000-000000000001')])]],
            'invalid level' => [[new IbDownlineLevelItem(0, [])]],
            'duplicate level' => [[new IbDownlineLevelItem(1, []), new IbDownlineLevelItem(1, [])]],
        ];
    }

    public function test_network_failure_is_retryable(): void
    {
        $network = Mockery::mock(ReferralNetworkServiceInterface::class);
        $network->shouldReceive('getDownline')->with('00000000-0000-4000-8000-000000000009', 2)->once()->andThrow(new \RuntimeException('private'));
        $this->expectException(NegativePnlReferralsUnavailableException::class);
        (new IamResolveNegativePnlReferralsAdapter($network))->resolve('00000000-0000-4000-8000-000000000009', 1);
    }

    private function adapter(IbDownlineResponse $data): IamResolveNegativePnlReferralsAdapter
    {
        $response = Mockery::mock(ActionResultInterface::class);
        $response->shouldReceive('isSuccess')->once()->andReturn(true);
        $response->shouldReceive('getMappedData')->with(IbDownlineResponse::class)->once()->andReturn($data);
        $network = Mockery::mock(ReferralNetworkServiceInterface::class);
        $network->shouldReceive('getDownline')->with('00000000-0000-4000-8000-000000000009', 2)->once()->andReturn($response);

        return new IamResolveNegativePnlReferralsAdapter($network);
    }
}
