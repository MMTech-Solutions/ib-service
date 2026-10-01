<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners\Kafka;

use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\Exceptions\CpaCaptureNotApplicableException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;

final class AuthAccountRegisteredTopicHandler implements TopicMessageHandlerInterface
{
    public function __construct(
        private readonly CaptureCpaContextPort $capture,
        private readonly ConfigRepository $config,
    ) {}

    public function topic(): string
    {
        return (string) $this->config->get('rewards.auth_account_registered.topic', 'auth.events.v1');
    }

    public function handle(ConsumerMessage $message): void
    {
        $body = $message->getBody();
        if (! is_array($body) || ($body['event_name'] ?? null) !== 'auth.account.registered' || ($body['schema_version'] ?? null) !== '1.0') {
            return;
        }

        $source = $body['source'] ?? null;
        $allowedSources = $this->config->get('rewards.auth_account_registered.allowed_sources', []);
        if (! is_string($source) || ! is_array($allowedSources) || ! in_array($source, $allowedSources, true)) {
            Log::warning('Rejected auth.account.registered producer.', ['event_name' => 'auth.account.registered']);

            return;
        }

        $payload = $body['payload'] ?? null;
        $referredUserId = is_array($payload) ? ($payload['user_id'] ?? null) : null;
        $ibUserId = is_array($payload) ? ($payload['ib_user_id'] ?? null) : null;
        if (! is_string($referredUserId) || ! $this->isUuid($referredUserId)) {
            Log::warning('Rejected auth.account.registered payload.', ['event_name' => 'auth.account.registered']);

            return;
        }
        if ($ibUserId === null) {
            Log::info('Ignored auth.account.registered without IB.', ['event_name' => 'auth.account.registered']);

            return;
        }
        if (! is_string($ibUserId) || ! $this->isUuid($ibUserId)) {
            Log::warning('Rejected auth.account.registered payload.', ['event_name' => 'auth.account.registered']);

            return;
        }

        try {
            $this->capture->execute(new CaptureCpaContextData(
                referred_user_id: $referredUserId,
                ib_user_id: $ibUserId,
                captured_at: CarbonImmutable::now('UTC')->toISOString(),
            ));
        } catch (CpaCaptureNotApplicableException $exception) {
            Log::info('Auth account is not CPA applicable.', ['reason' => $exception->getMessage()]);
        }
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
