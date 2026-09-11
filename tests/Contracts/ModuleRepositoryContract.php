<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleListQueryData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryQueryData;
use App\Features\Modules\Catalog\Enums\OperationalControlAction;
use App\Features\Modules\Catalog\Exceptions\DuplicateModuleCodeException;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\OperationalControlChange;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class ModuleRepositoryContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function repository(): ModuleRepositoryInterface;

    public function test_it_creates_recovers_by_id_and_code_and_paginates_a_module(): void
    {
        $repository = $this->repository();
        $module = $this->module('broker');
        $repository->create($module);

        $storedByCode = $repository->findByCode('broker');
        $storedById = $repository->findById($module->id);
        self::assertNotNull($storedByCode);
        self::assertNotNull($storedById);
        self::assertSame($module->id, $storedByCode->id);
        self::assertSame($module->code, $storedById->code);
        self::assertSame(['deposits'], array_map(static fn ($capability): string => $capability->code, $storedByCode->capabilities));

        $page = $repository->paginate(new ModuleListQueryData(search: 'BROK'));
        self::assertSame(1, $page->total);
        self::assertSame('broker', $page->modules[0]->code);
        self::assertNull($repository->findById((string) Str::uuid7()));
    }

    public function test_it_rejects_duplicate_codes(): void
    {
        $repository = $this->repository();
        $repository->create($this->module('broker'));

        $this->expectException(DuplicateModuleCodeException::class);
        $repository->create($this->module('broker'));
    }

    public function test_it_persists_administrative_and_independent_operational_states(): void
    {
        $repository = $this->repository();
        $module = $this->module('broker');
        $repository->create($module);

        $module->updateAdministrativeFields('Broker module', 'Updated description', $this->now());
        $module->pause($this->now());
        $module->deactivate($this->now());
        $repository->update($module, 1);

        $stored = $repository->findById($module->id);
        self::assertNotNull($stored);
        self::assertSame('Broker module', $stored->name);
        self::assertSame('Updated description', $stored->description);
        self::assertFalse($stored->isActive);
        self::assertSame(ProcessingStatus::Paused, $stored->processingStatus);
        self::assertSame(2, $stored->lockVersion);
    }

    public function test_it_rejects_an_obsolete_lock_version(): void
    {
        $repository = $this->repository();
        $repository->create($this->module('broker'));
        $firstWriter = $repository->findByCode('broker');
        $obsoleteWriter = $repository->findByCode('broker');
        self::assertNotNull($firstWriter);
        self::assertNotNull($obsoleteWriter);

        $firstWriter->deactivate($this->now());
        $repository->update($firstWriter, 1);

        $obsoleteWriter->deactivate($this->now());
        $this->expectException(ModuleConcurrencyException::class);
        $repository->update($obsoleteWriter, 1);
    }

    public function test_it_paginates_operational_history_in_reverse_chronological_order(): void
    {
        $repository = $this->repository();
        $module = $this->module('broker');
        $repository->create($module);
        $repository->appendOperationalChange($this->change($module, OperationalControlAction::Pause, '2026-09-11T10:00:00.000000Z'));
        $repository->appendOperationalChange($this->change($module, OperationalControlAction::Resume, '2026-09-11T11:00:00.000000Z'));

        $firstPage = $repository->paginateOperationalHistory(new ModuleOperationalHistoryQueryData($module->id, page: 1, perPage: 1));
        $secondPage = $repository->paginateOperationalHistory(new ModuleOperationalHistoryQueryData($module->id, page: 2, perPage: 1));

        self::assertSame(2, $firstPage->total);
        self::assertSame(2, $firstPage->lastPage);
        self::assertSame('resume', $firstPage->entries[0]->action);
        self::assertSame('pause', $secondPage->entries[0]->action);
    }

    public function test_operational_changes_are_isolated_by_module(): void
    {
        $repository = $this->repository();
        $broker = $this->module('broker');
        $copyTrading = $this->module('copy-trading');
        $repository->create($broker);
        $repository->create($copyTrading);
        $repository->appendOperationalChange($this->change($broker, OperationalControlAction::Deactivate, $this->now()));

        $brokerHistory = $repository->paginateOperationalHistory(new ModuleOperationalHistoryQueryData($broker->id));
        $copyTradingHistory = $repository->paginateOperationalHistory(new ModuleOperationalHistoryQueryData($copyTrading->id));

        self::assertSame(1, $brokerHistory->total);
        self::assertSame(0, $copyTradingHistory->total);
        self::assertTrue($repository->findById($copyTrading->id)?->isActive);
    }

    public function test_transaction_rolls_back_module_and_history_changes_after_an_error(): void
    {
        $repository = $this->repository();
        $module = $this->module('broker');
        $repository->create($module);

        try {
            $repository->transaction(function () use ($repository, $module): void {
                $module->deactivate($this->now());
                $repository->update($module, 1);
                $repository->appendOperationalChange($this->change($module, OperationalControlAction::Deactivate, $this->now()));
                throw new RuntimeException('Force rollback.');
            });
            self::fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Force rollback.', $exception->getMessage());
        }

        self::assertTrue($repository->findById($module->id)?->isActive);
        self::assertSame(1, $repository->findById($module->id)?->lockVersion);
        self::assertSame(0, $repository->paginateOperationalHistory(new ModuleOperationalHistoryQueryData($module->id))->total);
    }

    protected function module(string $code): Module
    {
        $now = $this->now();

        return Module::fromDefinition(
            id: (string) Str::uuid7(),
            definition: new ModuleDefinitionData(
                code: $code,
                name: ucfirst($code),
                description: null,
                capabilities: [new ModuleCapabilityDefinitionData('deposits', 'Deposits', null)],
            ),
            generateId: static fn (): string => (string) Str::uuid7(),
            now: $now,
        );
    }

    private function change(Module $module, OperationalControlAction $action, string $occurredAt): OperationalControlChange
    {
        return new OperationalControlChange(
            id: (string) Str::uuid7(),
            moduleId: $module->id,
            action: $action,
            actorIamId: (string) Str::uuid7(),
            reason: 'Operational reason',
            previousIsActive: true,
            previousProcessingStatus: ProcessingStatus::Running,
            nextIsActive: $action !== OperationalControlAction::Deactivate,
            nextProcessingStatus: $action === OperationalControlAction::Pause ? ProcessingStatus::Paused : ProcessingStatus::Running,
            occurredAt: $occurredAt,
        );
    }

    private function now(): string
    {
        return CarbonImmutable::now('UTC')->toISOString();
    }
}
