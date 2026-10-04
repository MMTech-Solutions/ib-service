<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Rewards\DTOs\RecordVolumeRewardEventData;
use App\Features\Rewards\Factories\VolumeRewardProcessingRepositoryFactory;
use App\Features\Rewards\Listeners\Kafka\TradingPositionClosedTopicHandler;
use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TradingPositionClosedTopicHandlerTest extends TestCase
{
    #[DataProvider('serializationHeaders')]
    public function test_it_records_deserialized_position_closed_events(array $headers): void
    {
        config()->set('rewards.volume.broker_module_id', '00000000-0000-7000-8000-000000000001');
        $repository = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $repository->shouldReceive('recordEvent')->once()->withArgs(function (RecordVolumeRewardEventData $data): bool {
            self::assertSame('position-1', $data->order_id);
            self::assertSame('login-1', $data->external_trader_id);
            self::assertSame(['event_name' => 'position_closed', 'topic' => 'trading-services.events.v1', 'partition' => 2, 'offset' => 8], $data->transport_snapshot);
            self::assertArrayNotHasKey('login', $data->transport_snapshot);
            self::assertArrayNotHasKey('payload', $data->transport_snapshot);

            return true;
        });
        app()->instance('rewards.volume-processing.repositories.postgresql', $repository);

        $handler = new TradingPositionClosedTopicHandler(
            new RecordVolumeRewardEventUseCase(app(VolumeRewardProcessingRepositoryFactory::class)),
            config(),
        );
        $handler->handle($this->message(
            ['id' => 'position-1', 'login' => 'login-1', 'email' => 'private@example.test'],
            $headers + ['event_name' => 'position_closed', 'login' => 'login-1'],
        ));
    }

    public static function serializationHeaders(): array
    {
        return [
            'avro' => [['content_type' => 'application/avro']],
            'json' => [['content_type' => 'application/json']],
            'absent' => [[]],
            'alternate header' => [['Content-Type' => 'Application/Avro']],
            'alternate value' => [['content_type' => ' application/avro ']],
            'array event name' => [['event_name' => ['position_closed']]],
        ];
    }

    public function test_it_ignores_other_events_and_rejects_invalid_position_closed_payloads(): void
    {
        config()->set('rewards.volume.broker_module_id', '00000000-0000-7000-8000-000000000001');
        $repository = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $repository->shouldNotReceive('recordEvent');
        app()->instance('rewards.volume-processing.repositories.postgresql', $repository);
        $handler = new TradingPositionClosedTopicHandler(
            new RecordVolumeRewardEventUseCase(app(VolumeRewardProcessingRepositoryFactory::class)),
            config(),
        );

        $handler->handle($this->message(['id' => 'position-1', 'login' => 'login-1'], ['content_type' => 'application/json']));
        foreach (['position_added', 'margin_level_updated', 'unknown', ''] as $eventName) {
            $handler->handle($this->message(['id' => 'position-1', 'login' => 'login-1'], ['event_name' => $eventName, 'content_type' => 'application/avro']));
        }
        $headers = ['event_name' => 'position_closed'];
        $handler->handle($this->message('invalid body', $headers));
        $handler->handle($this->message(['id' => 'position-1', 'order_id' => 'position-2', 'login' => 'login-1'], $headers));
        $handler->handle($this->message(['id' => 'position-1', 'login' => 'login-1'], $headers + ['login' => 'login-2']));
        $handler->handle($this->message(['id' => 'position-1'], $headers));
        $handler->handle($this->message(['login' => 'login-1'], $headers));
        config()->set('rewards.volume.broker_module_id', null);
        $handler->handle($this->message(['id' => 'position-1', 'login' => 'login-1'], $headers));
    }

    /** @param array<string, mixed> $headers */
    private function message(mixed $body, array $headers): ConsumerMessage
    {
        return new class($body, $headers) implements ConsumerMessage
        {
            public function __construct(private readonly mixed $body, private readonly array $headers) {}

            public function getKey(): mixed
            {
                return null;
            }

            public function getTopicName(): ?string
            {
                return 'trading-services.events.v1';
            }

            public function getPartition(): ?int
            {
                return 2;
            }

            public function getHeaders(): ?array
            {
                return $this->headers;
            }

            public function getMessageIdentifier(): string
            {
                return 'test';
            }

            public function getBody(): mixed
            {
                return $this->body;
            }

            public function getOffset(): ?int
            {
                return 8;
            }

            public function getTimestamp(): ?int
            {
                return null;
            }
        };
    }
}
