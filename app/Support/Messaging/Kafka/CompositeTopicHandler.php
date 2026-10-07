<?php

declare(strict_types=1);

namespace App\Support\Messaging\Kafka;

use InvalidArgumentException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;

final class CompositeTopicHandler implements TopicMessageHandlerInterface
{
    /** @param list<TopicMessageHandlerInterface> $handlers */
    public function __construct(private readonly string $topicName, private readonly array $handlers)
    {
        foreach ($handlers as $handler) {
            if ($handler->topic() !== $topicName) {
                throw new InvalidArgumentException('Composite Kafka handler topic mismatch.');
            }
        }
    }

    public function topic(): string
    {
        return $this->topicName;
    }

    public function handle(ConsumerMessage $message): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($message);
        }
    }
}
