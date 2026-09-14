<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Plans\Catalog\Contracts\Repositories\PlanRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\PlanListQueryData;
use App\Features\Plans\Catalog\Enums\PlanActorKind;
use App\Features\Plans\Catalog\Enums\PlanOperationalAction;
use App\Features\Plans\Catalog\Exceptions\DuplicatePlanCodeException;
use App\Features\Plans\Catalog\Exceptions\PlanConcurrencyException;
use App\Features\Plans\Catalog\Models\Plan;
use App\Features\Plans\Catalog\Models\PlanOperationalChange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class PlanRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): PlanRepositoryInterface;

    /** @return list<string> */
    abstract protected function moduleIds(): array;

    public function test_it_creates_recovers_and_paginates_a_plan(): void
    {
        $repository = $this->repository();
        $plan = $this->plan('mix', $this->moduleIds());
        $repository->create($plan);

        $storedByCode = $repository->findByCode('mix');
        $storedById = $repository->findById($plan->id);
        self::assertNotNull($storedByCode);
        self::assertNotNull($storedById);
        self::assertSame($plan->id, $storedByCode->id);
        self::assertSame($plan->code, $storedById->code);
        self::assertFalse($storedById->isActive);
        self::assertSame($this->moduleIds(), $storedById->moduleIds());

        $page = $repository->paginate(new PlanListQueryData(search: 'MI'));
        self::assertSame(1, $page->total);
        self::assertSame('mix', $page->plans[0]->code);
        self::assertNull($repository->findById((string) Str::uuid7()));
    }

    public function test_it_rejects_duplicate_codes(): void
    {
        $repository = $this->repository();
        $repository->create($this->plan('mix', []));

        $this->expectException(DuplicatePlanCodeException::class);
        $repository->create($this->plan('mix', []));
    }

    public function test_it_replaces_bindings_and_archives_without_removing_references(): void
    {
        $repository = $this->repository();
        $plan = $this->plan('broker-plan', [$this->moduleIds()[0]]);
        $repository->create($plan);

        $plan->replaceBindings($this->moduleIds(), static fn (): string => (string) Str::uuid7(), $this->now());
        $repository->update($plan, 1);

        $stored = $repository->findById($plan->id);
        self::assertNotNull($stored);
        self::assertSame($this->moduleIds(), $stored->moduleIds());
        self::assertSame(2, $stored->lockVersion);

        $stored->archive($this->now());
        $repository->update($stored, 2);

        self::assertNull($repository->findById($plan->id));
        self::assertTrue($repository->isModuleReferenced($this->moduleIds()[0]));
    }

    public function test_it_rejects_an_obsolete_lock_version(): void
    {
        $repository = $this->repository();
        $repository->create($this->plan('mix', []));
        $firstWriter = $repository->findByCode('mix');
        $obsoleteWriter = $repository->findByCode('mix');
        self::assertNotNull($firstWriter);
        self::assertNotNull($obsoleteWriter);

        $firstWriter->updateAdministrativeFields('Mix plan', 'Updated', $this->now());
        $repository->update($firstWriter, 1);

        $obsoleteWriter->updateAdministrativeFields('Stale', null, $this->now());
        $this->expectException(PlanConcurrencyException::class);
        $repository->update($obsoleteWriter, 1);
    }

    public function test_transaction_rolls_back_plan_and_history_changes_after_an_error(): void
    {
        $repository = $this->repository();
        $plan = $this->plan('mix', []);
        $repository->create($plan);

        try {
            $repository->transaction(function () use ($repository, $plan): void {
                $plan->updateAdministrativeFields('Changed', null, $this->now());
                $repository->update($plan, 1);
                $repository->appendOperationalChange($this->change($plan, PlanOperationalAction::Activate));
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        $stored = $repository->findById($plan->id);
        self::assertNotNull($stored);
        self::assertSame('Mix', $stored->name);
        self::assertSame(1, $stored->lockVersion);
    }

    public function test_all_active_excludes_inactive_and_archived_plans(): void
    {
        $repository = $this->repository();
        $active = $this->plan('active-plan', $this->moduleIds());
        $inactive = $this->plan('inactive-plan', []);
        $repository->create($active);
        $repository->create($inactive);

        $active->activate($this->summaries($this->moduleIds()), $this->now());
        $repository->update($active, 1);

        $found = array_map(static fn (Plan $plan): string => $plan->code, $repository->allActive());
        self::assertSame(['active-plan'], $found);
    }

    /**
     * @param  list<string>  $moduleIds
     */
    protected function plan(string $code, array $moduleIds): Plan
    {
        return Plan::create(
            id: (string) Str::uuid7(),
            code: $code,
            name: ucfirst($code),
            description: null,
            moduleIds: $moduleIds,
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $this->now(),
        );
    }

    private function change(Plan $plan, PlanOperationalAction $action): PlanOperationalChange
    {
        return new PlanOperationalChange(
            id: (string) Str::uuid7(),
            planId: $plan->id,
            action: $action,
            actorKind: PlanActorKind::Iam,
            actorIamId: (string) Str::uuid7(),
            reason: 'Operational reason',
            previousIsActive: false,
            nextIsActive: $action === PlanOperationalAction::Activate,
            causeEventId: null,
            causeModuleId: null,
            initiatingActorIamId: null,
            occurredAt: $this->now(),
        );
    }

    /**
     * @param  list<string>  $moduleIds
     * @return list<ModuleSummaryData>
     */
    private function summaries(array $moduleIds): array
    {
        return array_map(
            static fn (string $id): ModuleSummaryData => new ModuleSummaryData(
                id: $id,
                code: 'broker',
                name: 'Broker',
                is_active: true,
                processing_status: 'running',
            ),
            $moduleIds,
        );
    }

    private function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
