<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners\Kafka;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
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
        $body = $message->getBody();
        $headers = $message->getHeaders();
        $orderId = is_array($body) ? ($body['id'] ?? $body['position_id'] ?? null) : null;
        $traderId = $headers['login'] ?? (is_array($body) ? ($body['login'] ?? null) : null);
        $moduleId = $this->config->get('rewards.volume.broker_module_id');
        if (! is_scalar($orderId) || ! is_scalar($traderId) || ! is_string($moduleId) || $moduleId === '') {
            Log::warning('Rejected trading position closed reward event.');

            return;
        }
        $this->receipts->execute(new RecordVolumeRewardEventData($moduleId, (string) $orderId, (string) $traderId, ['id' => (string) $orderId, 'login' => (string) $traderId]));
    }
}
