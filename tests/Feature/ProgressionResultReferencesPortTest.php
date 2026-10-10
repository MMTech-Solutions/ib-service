<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Contracts\Data\V1\ResolveProgressionResultReferencesQueryData;
use App\Features\Progression\Contracts\Ports\Input\ResolveProgressionResultReferencesPort;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Catalog\Enums\SubscriptionActorKind;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProgressionResultReferencesPortTest extends TestCase
{
    use RefreshDatabase;

    public static function drivers(): array
    {
        return [['memory'], ['postgresql']];
    }

    #[DataProvider('drivers')]
    public function test_references_are_batched_and_restricted_to_the_subscription(string $driver): void
    {
        config()->set('progression.repository', $driver);
        $now = CarbonImmutable::parse('2026-10-01T00:00:00Z');
        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id]);
        $subscriptions = [];
        for ($index = 0; $index < 2; $index++) {
            $id = static fn (): string => (string) Str::uuid7();
            $subscription = Subscription::requestActive($id(), $id(), $plan->id, $program->id, $id(), $id(), SubscriptionActorKind::Iam, $id(), null, $id, $now->toISOString());
            app(SubscriptionRepositoryFactory::class)->make()->create($subscription);
            $subscriptions[] = $subscription;
        }
        $repository = app(ProgressionRunRepositoryFactory::class)->make();
        $run = $repository->findOrCreateRun($plan->id, ProgressionWindow::of($now->subDay(), $now), $now);
        $mine = $repository->findOrCreateResult($run->id, $subscriptions[0]->id, $now);
        $foreign = $repository->findOrCreateResult($run->id, $subscriptions[1]->id, $now);
        $nextRun = $repository->findOrCreateRun($plan->id, ProgressionWindow::of($now, $now->addDay()), $now->addDay());
        $next = $repository->findOrCreateResult($nextRun->id, $subscriptions[0]->id, $now->addDay());
        $port = app(ResolveProgressionResultReferencesPort::class);
        self::assertSame([], $port->execute(new ResolveProgressionResultReferencesQueryData($subscriptions[0]->id, [])));
        $references = $port->execute(new ResolveProgressionResultReferencesQueryData($subscriptions[0]->id, [$next->id, $mine->id, $foreign->id, (string) Str::uuid7(), $mine->id]));
        self::assertCount(2, $references);
        $byResult = [];
        foreach ($references as $reference) {
            $byResult[$reference->run_result_id] = $reference->run_id;
        }
        self::assertSame($run->id, $byResult[$mine->id]);
        self::assertSame($nextRun->id, $byResult[$next->id]);
        self::assertArrayNotHasKey($foreign->id, $byResult);
    }
}
