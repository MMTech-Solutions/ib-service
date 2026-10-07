<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners\Kafka;

use App\Features\Modules\Contracts\Ports\Input\ListModuleActivitySubscriptionsPort;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use App\Features\Modules\Contracts\Ports\Input\ValidateModuleActivitySubscriptionsPort;
use App\Features\Rewards\UseCases\RecordVolumeRewardEventUseCase;
use App\Support\Messaging\Kafka\CompositeTopicHandler;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class VolumeRewardKafkaSubscriptions
{
    private bool $registered = false;

    public function __construct(
        private readonly ListModuleActivitySubscriptionsPort $subscriptions,
        private readonly ValidateModuleActivitySubscriptionsPort $validator,
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $byTopic = [];
        foreach ($this->subscriptions->execute() as $subscription) {
            if (! preg_match('/^[a-zA-Z0-9._-]{1,249}$/D', $subscription->topic)
                || in_array($subscription->topic, ['.', '..'], true)
                || $subscription->topic === $this->snapshotTopic()) {
                continue;
            }
            $byTopic[$subscription->topic][] = $subscription;
        }
        $handlers = $this->config->get('rbac.consumer.handlers', []);
        foreach ($byTopic as $topic => $subscriptions) {
            $previous = $handlers[$topic] ?? null;
            $binding = 'volume-reward.kafka.'.hash('sha256', $topic);
            $this->container->bind($binding, function (Container $container) use ($topic, $subscriptions, $previous) {
                $volume = new VolumeRewardTopicHandler($topic, $subscriptions, $container->make(NormalizeVolumeRewardEventPort::class), $container->make(RecordVolumeRewardEventUseCase::class));

                return $previous === null ? $volume : new CompositeTopicHandler($topic, [$container->make($previous), $volume]);
            });
            $handlers[$topic] = $binding;
        }
        $this->config->set('rbac.consumer.handlers', $handlers);
        $this->registered = true;
    }

    public function validateForConsumption(): void
    {
        $this->validator->execute();
        foreach ($this->subscriptions->execute() as $subscription) {
            if ($subscription->topic === $this->snapshotTopic()) {
                throw new InvalidArgumentException('Module activity topic cannot be the reserved RBAC snapshot topic.');
            }
        }
    }

    private function snapshotTopic(): string
    {
        return trim((string) $this->config->get('rbac.consumer.topic')) ?: 'iam.rbac.snapshots.v1';
    }
}
