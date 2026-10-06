<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Rewards\Listeners\PublishCpaContextExpiredListener;
use App\Features\Rewards\Services\Pushers\Service\ServiceEventPusher;
use App\Support\Messaging\Contracts\MessagePublisherInterface;
use Mockery;
use Tests\TestCase;

final class PublishCpaContextExpiredListenerTest extends TestCase
{
    public function test_listener_publishes_service_event_envelope(): void
    {
        config([
            'rewards.events.topic' => 'ib-service.events.v1',
            'rewards.events.source' => 'ib-service',
        ]);
        $publisher = Mockery::mock(MessagePublisherInterface::class);
        $publisher->shouldReceive('publish')->once()->with(
            'ib-service.events.v1',
            Mockery::on(function (array $envelope): bool {
                return $envelope['event_name'] === ServiceEventPusher::CPA_CONTEXT_EXPIRED
                    && $envelope['schema_version'] === '1.0'
                    && $envelope['source'] === 'ib-service'
                    && $envelope['payload']['cpa_context_id'] === 'context-1'
                    && $envelope['payload']['reason'] === 'waiting_period_exceeded'
                    && $envelope['payload']['days_elapsed'] === 3
                    && $envelope['payload']['expiration_days'] === 2;
            }),
            'context-1',
            ['event_name' => ServiceEventPusher::CPA_CONTEXT_EXPIRED],
        );
        $event = new CpaContextExpired(
            'event-1',
            'context-1',
            'referred',
            'ib',
            'plan',
            'program',
            'rule',
            'version',
            '2026-10-01T00:00:00+00:00',
            2,
            3,
            'waiting_period_exceeded',
            '2026-10-04T12:00:00+00:00',
        );

        (new PublishCpaContextExpiredListener(new ServiceEventPusher($publisher)))->handle($event);
    }
}
