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
use Tests\TestCase;

final class TradingPositionClosedTopicHandlerTest extends TestCase
{
    public function test_it_records_only_technical_references_from_the_avro_v1_payload(): void
    {
        config()->set('rewards.volume.broker_module_id', '00000000-0000-7000-8000-000000000001');
        $repository = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $repository->shouldReceive('recordEvent')->once()->withArgs(function (RecordVolumeRewardEventData $data): bool {
            self::assertSame('position-1', $data->order_id);
            self::assertSame('login-1', $data->external_trader_id);
            self::assertSame('com.mmt.platform.PositionClosed', $data->transport_snapshot['contract_subject']);
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
            ['content_type' => 'application/avro', 'login' => 'login-1'],
        ));
    }

    public function test_it_rejects_non_avro_and_mismatched_identifiers(): void
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
        $handler->handle($this->message(['id' => 'position-1', 'order_id' => 'position-2', 'login' => 'login-1'], ['content_type' => 'application/avro']));
        $handler->handle($this->message(['id' => 'position-1', 'login' => 'login-1'], ['content_type' => 'application/avro', 'login' => 'login-2']));
    }

    /** @param array<string, mixed> $body @param array<string, mixed> $headers */
    private function message(array $body, array $headers): ConsumerMessage
    {
        return new class($body, $headers) implements ConsumerMessage
        {
            public function __construct(private readonly array $body, private readonly array $headers) {}

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
