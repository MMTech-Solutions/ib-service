<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\Services\Adapters\IamResolveReferralUplineAdapter;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\Contracts\ReferralNetworkServiceInterface;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbReferralUserItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineLevelItem;
use Mmt\IamServiceSdk\Domains\ReferralNetwork\ObjectResponses\IbUplineResponse;
use Mmt\IamServiceSdk\TransportDrivers\Contracts\ActionResultInterface;
use Mockery;
use Tests\TestCase;

final class ReferralUplineAdapterTest extends TestCase
{
    public function test_it_normalizes_iam_levels(): void
    {
        $response = Mockery::mock(ActionResultInterface::class);
        $response->shouldReceive('isSuccess')->once()->andReturnTrue();
        $response->shouldReceive('getMappedData')->once()->andReturn(new IbUplineResponse(2, [new IbUplineLevelItem(1, new IbReferralUserItem('direct')), new IbUplineLevelItem(2, new IbReferralUserItem('root'))]));
        $service = Mockery::mock(ReferralNetworkServiceInterface::class);
        $service->shouldReceive('getUpline')->once()->with('source')->andReturn($response);
        $result = (new IamResolveReferralUplineAdapter($service))->resolve(new ResolveReferralUplineQueryData('source'));
        self::assertTrue($result->isResolved());
        self::assertSame([0, 1], array_map(fn ($item): int => $item->distribution_level, $result->beneficiaries));
    }

    public function test_it_returns_a_normalized_failure_without_sdk_details(): void
    {
        $response = Mockery::mock(ActionResultInterface::class);
        $response->shouldReceive('isSuccess')->once()->andReturnFalse();
        $service = Mockery::mock(ReferralNetworkServiceInterface::class);
        $service->shouldReceive('getUpline')->once()->with('source')->andReturn($response);

        $result = (new IamResolveReferralUplineAdapter($service))->resolve(new ResolveReferralUplineQueryData('source'));

        self::assertFalse($result->isResolved());
        self::assertSame('unavailable', $result->failure_code);
        self::assertSame([], $result->beneficiaries);
    }

    public function test_the_provider_exposes_the_upline_port_through_gateways(): void
    {
        self::assertInstanceOf(ResolveReferralUplinePort::class, $this->app->make(ResolveReferralUplinePort::class));
        self::assertInstanceOf(ResolveReferralUplinePort::class, $this->app->make(ProgressionInterFeatureGateways::class)->referralUpline());
    }
}
