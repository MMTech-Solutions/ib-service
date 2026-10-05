<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners\Kafka;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;

final class TradingPositionClosedTopicHandler implements TopicMessageHandlerInterface
{
    public function __construct(private readonly RecordVolumeRewardEventUseCase $receipts, private readonly ConfigRepository $config) {}

    public function topic(): string
    {
        return (string) $this->config->get('rewards.volume.trading_topic', 'trading-services.events.v1');
    }

    public function handle(ConsumerMessage $message): void
    {
        $clock = app(DomainClock::class);
        $clock->end();
        try {
            $clock->begin();
            $this->handleMessage($message);
        } finally {
            $clock->end();
        }
    }

    private function handleMessage(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders() ?? [];
        $eventName = $this->header($headers, 'event_name');
        if ($eventName !== 'position_closed') {
            return;
        }
        $body = $message->getBody();
        if (! is_array($body)) {
            Log::warning('Rejected trading position closed reward event contract.');

            return;
        }

        $id = $body['id'] ?? null;
        $orderId = $body['order_id'] ?? $id;
        if ($id !== null && isset($body['order_id']) && (string) $id !== (string) $body['order_id']) {
            Log::warning('Rejected trading position closed reward event identifiers.');

            return;
        }

        $headerLogin = $this->header($headers, 'login');
        $payloadLogin = $body['login'] ?? null;
        if ($headerLogin !== null && $payloadLogin !== null && $headerLogin !== (string) $payloadLogin) {
            Log::warning('Rejected trading position closed reward event login mismatch.');

            return;
        }
        $traderId = $payloadLogin ?? $headerLogin;
        $moduleId = $this->config->get('rewards.volume.broker_module_id');
        if (! is_scalar($orderId) || trim((string) $orderId) === '' || ! is_scalar($traderId) || trim((string) $traderId) === '' || ! is_string($moduleId) || $moduleId === '') {
            Log::warning('Rejected trading position closed reward event.');

            return;
        }
        $this->receipts->execute(new RecordVolumeRewardEventData(
            $moduleId,
            (string) $orderId,
            (string) $traderId,
            [
                'event_name' => $eventName,
                'topic' => $message->getTopicName(),
                'partition' => $message->getPartition(),
                'offset' => $message->getOffset(),
            ],
        ));
    }

    /** @param array<string, mixed> $headers */
    private function header(array $headers, string $name): ?string
    {
        $value = $headers[$name] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
