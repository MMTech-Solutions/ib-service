<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Pushers\Service;

use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Support\Messaging\Contracts\MessagePublisherInterface;

final class ServiceEventPusher
{
    public const CPA_CONTEXT_EXPIRED = 'ib.cpa.context.expired';

    public function __construct(private readonly MessagePublisherInterface $publisher) {}

    public function pushCpaContextExpired(CpaContextExpired $event): void
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.events.topic', 'rewards.events.source']);
        $this->publisher->publish(
            (string) $settings->get('rewards.events.topic'),
            [
                'event_id' => $event->eventId,
                'event_name' => self::CPA_CONTEXT_EXPIRED,
                'schema_version' => '1.0',
                'occurred_at' => $event->occurredAt,
                'source' => (string) $settings->get('rewards.events.source'),
                'payload' => [
                    'cpa_context_id' => $event->cpaContextId,
                    'referred_user_id' => $event->referredUserId,
                    'ib_user_id' => $event->ibUserId,
                    'plan_id' => $event->planId,
                    'program_id' => $event->programId,
                    'rule_id' => $event->ruleId,
                    'rule_version_id' => $event->ruleVersionId,
                    'captured_at' => $event->capturedAt,
                    'expiration_days' => $event->expirationDays,
                    'days_elapsed' => $event->daysElapsed,
                    'reason' => $event->reason,
                ],
            ],
            $event->cpaContextId,
            ['event_name' => self::CPA_CONTEXT_EXPIRED],
        );
    }
}
