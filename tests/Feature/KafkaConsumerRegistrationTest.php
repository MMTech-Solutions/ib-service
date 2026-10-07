<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Data\V1\ModuleActivitySubscriptionData;
use App\Features\Rewards\Listeners\Kafka\VolumeRewardKafkaSubscriptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Junges\Kafka\Consumers\Builder;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Mmtech\Rbac\Console\Commands\RbacConsumeSnapshotsCommand;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class KafkaConsumerRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('modules.sources.broker.topic', 'broker.test');
        config()->set('modules.sources.copy_trading.topic', 'copy.test');
        config()->set('rbac.consumer.enabled', true);
        config()->set('kafka.sasl.username', null);
        app(Kernel::class)->rerouteSymfonyCommandEvents();
        Http::preventStrayRequests();
    }

    public function test_original_consumer_discovers_topics_and_preserves_its_options(): void
    {
        $consumer = Mockery::mock(MessageConsumer::class);
        $consumer->shouldReceive('consume')->once();
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('usingDeserializer')->once()->andReturnSelf();
        $builder->shouldReceive('withHandler')->once()->andReturnSelf();
        $builder->shouldReceive('withMaxMessages')->once()->with(2)->andReturnSelf();
        $builder->shouldReceive('stopAfterLastMessage')->once()->andReturnSelf();
        $builder->shouldReceive('build')->once()->andReturn($consumer);
        Kafka::shouldReceive('consumer')->once()->withArgs(function ($topics, $group, $brokers): bool {
            self::assertContains('broker.test', $topics);
            self::assertContains('copy.test', $topics);
            self::assertContains(config('rbac.consumer.topic'), $topics);
            self::assertContains(config('rewards.auth_account_registered_topic', 'auth.events.v1'), $topics);

            return true;
        })->andReturn($builder);
        self::assertSame(0, Artisan::call('rbac:consume-snapshots', ['--stop-after-last-message' => true, '--max-messages' => 2]));
        self::assertInstanceOf(RbacConsumeSnapshotsCommand::class, Artisan::all()['rbac:consume-snapshots']);
        $registry = app(TopicHandlerRegistry::class);
        $handlers = config('rbac.consumer.handlers');
        app(VolumeRewardKafkaSubscriptions::class)->register();
        self::assertSame($handlers, config('rbac.consumer.handlers'));
        self::assertSame($registry, app(TopicHandlerRegistry::class));
        Http::assertNothingSent();
    }

    public static function invalidSubscriptions(): iterable
    {
        yield 'empty topic' => ['broker', '', 'position_closed', 1, 'broker_closed_volume_v1', false];
        yield 'malformed topic' => ['broker', 'invalid topic', 'position_closed', 1, 'broker_closed_volume_v1', false];
        yield 'reserved topic' => ['broker', 'iam.rbac.snapshots.v1', 'position_closed', 1, 'broker_closed_volume_v1', false];
        yield 'unknown adapter' => ['broker', 'broker.test', 'position_closed', 1, 'missing_adapter', false];
        yield 'ambiguous route' => ['broker', 'broker.test', 'position_closed', 1, 'broker_closed_volume_v1', true];
        yield 'wrong module' => ['copy_trading', 'broker.test', 'position_closed', 1, 'broker_closed_volume_v1', false];
        yield 'empty event' => ['broker', 'broker.test', '', 1, 'broker_closed_volume_v1', false];
        yield 'invalid version' => ['broker', 'broker.test', 'position_closed', 0, 'broker_closed_volume_v1', false];
    }

    #[DataProvider('invalidSubscriptions')]
    public function test_invalid_declarations_fail_before_kafka_is_accessed(string $module, string $topic, string $event, int $version, string $adapter, bool $duplicate): void
    {
        config()->set('rbac.consumer.topic', 'iam.rbac.snapshots.v1');
        $subscription = new ModuleActivitySubscriptionData($module, $topic, $event, $version, $adapter);
        $definition = new ModuleDefinitionData('broker', 'Broker', null, [
            new ModuleCapabilityDefinitionData('closed_trading_volume', 'Volume', null, $duplicate ? [$subscription, $subscription] : [$subscription]),
        ]);
        app()->instance(ModuleDefinitionRegistry::class, new ModuleDefinitionRegistry([$definition]));
        Kafka::shouldReceive('consumer')->never();
        $this->expectException(\InvalidArgumentException::class);
        Artisan::call('rbac:consume-snapshots', ['--stop-after-last-message' => true]);
    }

    public function test_pending_topic_does_not_block_other_commands_or_help(): void
    {
        config()->set('modules.sources.copy_trading.topic', '');
        Kafka::shouldReceive('consumer')->never();
        self::assertSame(0, Artisan::call('list', ['--raw' => true]));
        self::assertSame(0, Artisan::call('route:list', ['--except-vendor' => true, '--json' => true]));
        self::assertSame(0, Artisan::call('rbac:consume-snapshots', ['--help' => true]));
        Http::assertNothingSent();
    }

    public function test_disabled_consumer_does_not_prepare_or_validate_volume_topics(): void
    {
        config()->set('modules.sources.copy_trading.topic', '');
        config()->set('rbac.consumer.enabled', false);
        $handlers = config('rbac.consumer.handlers');
        Kafka::shouldReceive('consumer')->never();
        self::assertSame(0, Artisan::call('rbac:consume-snapshots'));
        self::assertSame($handlers, config('rbac.consumer.handlers'));
    }

    public function test_http_boot_does_not_require_valid_kafka_topics(): void
    {
        config()->set('modules.sources.copy_trading.topic', '');
        Kafka::shouldReceive('consumer')->never();
        $this->get('/')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_configuration_cache_keeps_only_static_handler_bindings(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('APP_CONFIG_CACHE');
        $path = tempnam(sys_get_temp_dir(), 'ib-kafka-config-');
        unlink($path);
        $repository->set('APP_CONFIG_CACHE', $path);
        app()->addAbsoluteCachePathPrefix('C:');
        try {
            self::assertSame($path, app()->getCachedConfigPath());
            self::assertSame(0, Artisan::call('config:cache'));
            $cached = require $path;
            foreach ($cached['rbac']['consumer']['handlers'] as $binding) {
                self::assertStringNotContainsString('volume-reward.kafka.', $binding);
            }
            $fresh = require base_path('bootstrap/app.php');
            $fresh->addAbsoluteCachePathPrefix('C:');
            $kernel = $fresh->make(Kernel::class);
            $kernel->bootstrap();
            self::assertTrue($fresh->configurationIsCached());
            self::assertSame(0, $kernel->call('list', ['--raw' => true]));
        } finally {
            if ($previous === null) {
                $repository->clear('APP_CONFIG_CACHE');
            } else {
                $repository->set('APP_CONFIG_CACHE', $previous);
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
