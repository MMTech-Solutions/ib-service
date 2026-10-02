<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions\Catalog;

use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HistoricalSubscriptionRewardRatesTest extends TestCase
{
    public function test_it_resolves_rates_effective_before_at_and_after_changes(): void
    {
        config()->set('subscriptions.repository', 'memory');
        $repository = app(SubscriptionRepositoryFactory::class)->make();
        $planId = (string) Str::uuid7();
        $programId = (string) Str::uuid7();
        $userId = (string) Str::uuid7();
        $repository->registerProgram($programId, $planId);
        $subscription = Subscription::requestActive(
            id: (string) Str::uuid7(),
            externalUserId: $userId,
            planId: $planId,
            programId: $programId,
            placementId: (string) Str::uuid7(),
            operationId: (string) Str::uuid7(),
            actorKind: SubscriptionActorKind::Iam,
            actorExternalUserId: $userId,
            reason: null,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: '2026-10-01T00:00:00+00:00',
        );
        $repository->create($subscription);

        $writer = $repository->findById($subscription->id);
        self::assertNotNull($writer);
        $writer->updateRewardRates('0.8', true, '1.5', (string) Str::uuid7(), SubscriptionActorKind::Iam, (string) Str::uuid7(), null, static fn (): string => (string) Str::uuid7(), '2026-10-01T01:00:00+00:00');
        $repository->save($writer, 1);
        $writer->updateRewardRates('0.6', false, '2', (string) Str::uuid7(), SubscriptionActorKind::Iam, (string) Str::uuid7(), null, static fn (): string => (string) Str::uuid7(), '2026-10-01T02:00:00+00:00');
        $repository->save($writer, 2);

        $port = app(ResolveSubscriptionContextPort::class);
        $before = $port->resolve(new ResolveSubscriptionContextQueryData($userId, '2026-10-01T00:30:00+00:00'))->context;
        $atFirst = $port->resolve(new ResolveSubscriptionContextQueryData($userId, '2026-10-01T01:00:00+00:00'))->context;
        $afterSecond = $port->resolve(new ResolveSubscriptionContextQueryData($userId, '2026-10-01T02:30:00+00:00'))->context;

        self::assertSame(['1.00000000', false, '1.00000000'], [$before?->personal_rate, $before?->is_master, $before?->master_rate]);
        self::assertSame(['0.80000000', true, '1.50000000'], [$atFirst?->personal_rate, $atFirst?->is_master, $atFirst?->master_rate]);
        self::assertSame(['0.60000000', false, '2.00000000'], [$afterSecond?->personal_rate, $afterSecond?->is_master, $afterSecond?->master_rate]);
    }
}
