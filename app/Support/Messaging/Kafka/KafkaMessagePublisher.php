<?php

declare(strict_types=1);

namespace App\Support\Messaging\Kafka;

use App\Support\Messaging\Contracts\MessagePublisherInterface;
use Mmtech\Rbac\Kafka\KafkaEventPublisher;

final class KafkaMessagePublisher implements MessagePublisherInterface
{
    public function __construct(private readonly KafkaEventPublisher $publisher) {}

    /** @param array<string, mixed> $payload @param array<string, string> $headers */
    public function publish(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->publisher->publish($topic, $payload, $key, $headers);
    }
}
