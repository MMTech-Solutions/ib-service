<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;
use App\Features\Modules\Contracts\Ports\Input\ListModuleActivitySubscriptionsPort;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use App\Features\Modules\Contracts\Ports\Input\ValidateModuleActivitySubscriptionsPort;
use App\Features\Rewards\Listeners\Kafka\VolumeRewardKafkaSubscriptions;
use App\Features\Rewards\Listeners\Kafka\VolumeRewardTopicHandler;
use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mmtech\Rbac\Kafka\Contracts\TopicMessageHandlerInterface;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\VolumeRewardEventFixtures;
use Tests\TestCase;

final class VolumeRewardProviderEventsTest extends TestCase
{
    use RefreshDatabase, VolumeRewardEventFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('modules.sources.broker.topic', 'broker.test');
        config()->set('modules.sources.copy_trading.topic', 'copy.test');
        config()->set('rbac.consumer.enabled', true);
        config()->set('rbac.consumer.handlers', array_filter(config('rbac.consumer.handlers', []), static fn ($binding): bool => ! str_starts_with($binding, 'volume-reward.kafka.')));
        app()->forgetInstance(TopicHandlerRegistry::class);
        app()->forgetInstance(VolumeRewardKafkaSubscriptions::class);
        $this->artisan('modules:sync')->assertSuccessful();
    }

    public static function providers(): iterable
    {
        yield 'Broker' => ['broker', 'broker.test'];
        yield 'Copy Trading' => ['copy_trading', 'copy.test'];
    }

    #[DataProvider('providers')]
    public function test_complete_events_are_persisted_without_http_and_duplicates_do_not_reset_state(string $provider, string $topic): void
    {
        Http::preventStrayRequests();
        $handler = $this->handler($topic);
        $handler->handle($this->message($topic, $this->body($provider)));
        $receipt = DB::table('volume_reward_event_receipts')->first();
        $snapshot = json_decode($receipt->activity, true);
        self::assertSame($provider.':server_group:group:symbol:symbol', $snapshot['instrument_reference']);
        self::assertSame('2026-10-06T10:00:00.123000Z', $snapshot['occurred_at']);
        self::assertSame('1.5', $snapshot['quantity']);
        self::assertSame('12.5', $snapshot['broker_granted_commission']);
        DB::table('volume_reward_event_receipts')->where('id', $receipt->id)->update(['status' => 'processed']);
        $handler->handle($this->message($topic, $this->body($provider), 9));
        self::assertSame(1, DB::table('volume_reward_event_receipts')->count());
        self::assertSame('processed', DB::table('volume_reward_event_receipts')->value('status'));
        self::assertSame(8, json_decode(DB::table('volume_reward_event_receipts')->value('transport_snapshot'), true)['offset']);
        Http::assertNothingSent();
    }

    public function test_conflicting_redelivery_is_recorded_without_replacing_the_original_evidence(): void
    {
        $handler = $this->handler('broker.test');
        $handler->handle($this->message('broker.test', $this->body()));
        $changed = $this->body();
        $changed['activity']['quantity'] = '9';
        $handler->handle($this->message('broker.test', $changed, 9));
        $receipt = DB::table('volume_reward_event_receipts')->first();
        self::assertSame('1.5', json_decode($receipt->activity, true)['quantity']);
        self::assertSame('9', json_decode($receipt->conflict_snapshot, true)['activity']['quantity']);
        self::assertSame('VOLUME_ACTIVITY_CONFLICT', $receipt->last_error_code);
    }

    public function test_paused_and_inactive_modules_still_receive_their_evidence(): void
    {
        DB::table('modules')->where('code', 'broker')->update(['is_active' => false, 'processing_status' => 'paused']);
        $this->handler('broker.test')->handle($this->message('broker.test', $this->body()));
        self::assertSame(1, DB::table('volume_reward_event_receipts')->count());
    }

    public static function invalidContracts(): iterable
    {
        yield 'schema string' => ['schema_version', '1'];
        yield 'unsupported schema' => ['schema_version', 2];
        yield 'provider mismatch' => ['provider_code', 'copy_trading'];
        yield 'event identity' => ['event_id', 'invalid'];
        yield 'quantity float' => ['activity.quantity', 1.5];
        yield 'quantity negative' => ['activity.quantity', '-1'];
        yield 'quantity exponent' => ['activity.quantity', '1e3'];
        yield 'quantity missing' => ['activity.quantity', null];
        yield 'commission negative' => ['activity.broker_granted_commission', '-1'];
        yield 'commission missing' => ['activity.broker_granted_commission', null];
        yield 'currency missing' => ['activity.currency_code', null];
        yield 'currency malformed' => ['activity.currency_code', 'usd'];
        yield 'precision string' => ['activity.currency_precision', '2'];
        yield 'precision invalid' => ['activity.currency_precision', 11];
        yield 'date invalid' => ['activity.occurred_at', '2026-02-30T10:00:00Z'];
        yield 'date non UTC' => ['activity.occurred_at', '2026-10-06T10:00:00+02:00'];
        yield 'wrong namespace' => ['activity.source_activity_id', 'copy_trading:position:abc'];
        yield 'user identity' => ['activity.subject_external_user_id', 'login-1'];
        yield 'metric mismatch' => ['activity.metric_code', 'profit'];
        yield 'unit mismatch' => ['activity.unit_code', 'USD'];
    }

    #[DataProvider('invalidContracts')]
    public function test_invalid_events_do_not_create_receipts(string $path, mixed $value): void
    {
        $body = $this->body();
        data_set($body, $path, $value);
        $this->handler('broker.test')->handle($this->message('broker.test', $body));
        self::assertSame(0, DB::table('volume_reward_event_receipts')->count());
    }

    public function test_unregistered_events_are_ignored_and_persistence_failures_propagate(): void
    {
        $this->handler('broker.test')->handle($this->message('broker.test', $this->body(), 8, 'other_event'));
        self::assertSame(0, DB::table('volume_reward_event_receipts')->count());
        $repository = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $repository->shouldReceive('recordEvent')->once()->andThrow(new \RuntimeException('database unavailable'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $repository);
        $this->expectExceptionMessage('database unavailable');
        $this->handler('broker.test')->handle($this->message('broker.test', $this->body()));
    }

    public function test_topics_are_discovered_from_capabilities_and_existing_handlers_are_preserved(): void
    {
        $existing = config('rbac.consumer.handlers');
        app(VolumeRewardKafkaSubscriptions::class)->register();
        $topics = app(TopicHandlerRegistry::class)->topicsToSubscribe();
        self::assertContains('broker.test', $topics);
        self::assertContains('copy.test', $topics);
        foreach (array_keys($existing) as $topic) {
            self::assertContains($topic, $topics);
        }
        self::assertNotContains('trading-services.events.v1', $topics);
    }

    public function test_distinct_events_share_a_topic_and_duplicate_routes_are_rejected(): void
    {
        $subscription = new ModuleActivitySubscriptionData('broker', 'shared.test', 'position_closed', 1, 'broker_closed_volume_v1');
        $other = new ModuleActivitySubscriptionData('broker', 'shared.test', 'another_event', 1, 'broker_closed_volume_v1');
        $definition = new ModuleDefinitionData('broker', 'Broker', null, [new ModuleCapabilityDefinitionData('closed_trading_volume', 'Volume', null, [$subscription, $other])]);
        app()->instance(ModuleDefinitionRegistry::class, new ModuleDefinitionRegistry([$definition]));
        self::assertCount(2, app(ListModuleActivitySubscriptionsPort::class)->execute());
        $duplicate = new ModuleDefinitionData('broker', 'Broker', null, [new ModuleCapabilityDefinitionData('closed_trading_volume', 'Volume', null, [$subscription, $subscription])]);
        app()->instance(ModuleDefinitionRegistry::class, new ModuleDefinitionRegistry([$duplicate]));
        app()->forgetInstance(ListModuleActivitySubscriptionsPort::class);
        app()->forgetInstance(ValidateModuleActivitySubscriptionsPort::class);
        $this->expectException(\InvalidArgumentException::class);
        app(ValidateModuleActivitySubscriptionsPort::class)->execute();
    }

    public function test_volume_dispatch_composes_with_an_existing_handler_on_the_same_topic(): void
    {
        $message = $this->message('broker.test', $this->body());
        $existing = Mockery::mock(TopicMessageHandlerInterface::class);
        $existing->shouldReceive('topic')->andReturn('broker.test');
        $existing->shouldReceive('handle')->once()->with($message);
        app()->instance('test.previous.kafka.handler', $existing);
        $handlers = config('rbac.consumer.handlers', []);
        $handlers['broker.test'] = 'test.previous.kafka.handler';
        config()->set('rbac.consumer.handlers', $handlers);
        app()->forgetInstance(TopicHandlerRegistry::class);
        app()->forgetInstance(VolumeRewardKafkaSubscriptions::class);
        config()->set('rbac.consumer.handlers', array_filter(config('rbac.consumer.handlers', []), static fn ($binding): bool => ! str_starts_with($binding, 'volume-reward.kafka.')));
        app(VolumeRewardKafkaSubscriptions::class)->register();
        app(TopicHandlerRegistry::class)->handle($message);
        self::assertSame(1, DB::table('volume_reward_event_receipts')->count());
    }

    public function test_empty_topic_fails_subscription_discovery(): void
    {
        config()->set('modules.sources.copy_trading.topic', '');
        $this->expectException(\InvalidArgumentException::class);
        app(ValidateModuleActivitySubscriptionsPort::class)->execute();
    }

    private function handler(string $topic): VolumeRewardTopicHandler
    {
        $subscriptions = array_values(array_filter(app(ListModuleActivitySubscriptionsPort::class)->execute(), static fn ($item): bool => $item->topic === $topic));

        return new VolumeRewardTopicHandler($topic, $subscriptions, app(NormalizeVolumeRewardEventPort::class), app(RecordVolumeRewardEventUseCase::class));
    }

    private function message(string $topic, mixed $body, int $offset = 8, string $name = 'position_closed'): ConsumerMessage
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event_name' => $name]);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('getTopicName')->andReturn($topic);
        $message->shouldReceive('getPartition')->andReturn(2);
        $message->shouldReceive('getOffset')->andReturn($offset);

        return $message;
    }
}
