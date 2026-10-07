<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Rewards\Listeners\Kafka\VolumeRewardKafkaSubscriptions;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Mmtech\Rbac\Kafka\TopicHandlerRegistry;

final class KafkaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VolumeRewardKafkaSubscriptions::class);
        $this->app->singleton(TopicHandlerRegistry::class, function (): TopicHandlerRegistry {
            return (new \ReflectionClass(TopicHandlerRegistry::class))->newLazyProxy(function (): TopicHandlerRegistry {
                if ((bool) config('rbac.consumer.enabled', true)) {
                    $this->app->make(VolumeRewardKafkaSubscriptions::class)->register();
                }

                return new TopicHandlerRegistry($this->app);
            });
        });
    }

    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== 'rbac:consume-snapshots'
                || ! (bool) config('rbac.consumer.enabled', true)
                || $event->input->hasParameterOption(['--help', '-h'], true)) {
                return;
            }
            $this->app->make(VolumeRewardKafkaSubscriptions::class)->validateForConsumption();
        });
    }
}
