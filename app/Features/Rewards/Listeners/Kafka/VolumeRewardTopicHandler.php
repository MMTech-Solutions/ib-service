<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners\Kafka;

use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;

final class VolumeRewardTopicHandler implements TopicMessageHandlerInterface
{
    /** @param list<ModuleActivitySubscriptionData> $subscriptions */
    public function __construct(
        private readonly string $topicName,
        private readonly array $subscriptions,
        private readonly NormalizeVolumeRewardEventPort $normalizer,
        private readonly RecordVolumeRewardEventUseCase $receipts,
    ) {}

    public function topic(): string
    {
        return $this->topicName;
    }

    public function handle(ConsumerMessage $message): void
    {
        $header = ($message->getHeaders() ?? [])['event_name'] ?? null;
        $eventName = is_array($header) ? ($header[0] ?? null) : $header;
        if (! is_string($eventName) || ! array_filter($this->subscriptions, static fn ($subscription): bool => $subscription->event_name === $eventName)) {
            return;
        }
        $clock = app(DomainClock::class);
        $clock->end();
        try {
            $clock->begin();
            $body = $message->getBody();
            if (! is_array($body)) {
                throw InvalidVolumeRewardActivityException::create();
            }
            $event = $this->normalizer->execute(new VolumeRewardEventQueryData($this->topicName, $eventName, $body));
        } catch (InvalidVolumeRewardActivityException) {
            Log::warning('Rejected volume reward event contract.', ['topic' => $this->topicName]);

            return;
        } finally {
            $clock->end();
        }

        $this->receipts->execute(new RecordVolumeRewardEventData($event->event_id, $event->schema_version, $event->activity, [
            'event_name' => $eventName, 'topic' => $message->getTopicName(),
            'partition' => $message->getPartition(), 'offset' => $message->getOffset(),
        ]));
    }
}
