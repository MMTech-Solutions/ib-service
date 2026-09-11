<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Repositories\PostgreSql;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleReferenceGuardInterface;
use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\DTOs\ModuleListQueryData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalChangeData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryPageData;
use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryQueryData;
use App\Features\Modules\Catalog\DTOs\ModulesPageData;
use App\Features\Modules\Catalog\Enums\OperationalControlAction;
use App\Features\Modules\Catalog\Exceptions\DuplicateModuleCodeException;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\ModuleCapability;
use App\Features\Modules\Catalog\Models\OperationalControlChange;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleCapabilityRecord;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleOperationalChangeRecord;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlModuleRepository implements ModuleRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ModuleReferenceGuardInterface $referenceGuard,
    ) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function all(): array
    {
        return ModuleRecord::query()
            ->with(['capabilities' => fn ($query) => $query->orderBy('code')])
            ->orderBy('code')
            ->get()
            ->map(fn (ModuleRecord $record): Module => $this->hydrate($record))
            ->all();
    }

    public function findById(string $id): ?Module
    {
        $record = ModuleRecord::query()->with('capabilities')->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByCode(string $code): ?Module
    {
        $record = ModuleRecord::query()->with('capabilities')->where('code', $code)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(Module $module): void
    {
        try {
            ModuleRecord::query()->create($this->moduleAttributes($module));
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateModuleCodeException::forCode($module->code);
        }

        $capabilities = $this->capabilityAttributes($module);
        if ($capabilities !== []) {
            ModuleCapabilityRecord::query()->insert($capabilities);
        }
    }

    public function update(Module $module, int $expectedLockVersion): void
    {
        $nextLockVersion = $expectedLockVersion + 1;
        $affected = ModuleRecord::query()
            ->whereKey($module->id)
            ->where('lock_version', $expectedLockVersion)
            ->update([
                'name' => $module->name,
                'description' => $module->description,
                'is_active' => $module->isActive,
                'processing_status' => $module->processingStatus->value,
                'lock_version' => $nextLockVersion,
                'updated_at' => $module->updatedAt,
            ]);

        if ($affected !== 1) {
            throw ModuleConcurrencyException::forModule($module->id);
        }

        $capabilities = $this->capabilityAttributes($module);
        if ($capabilities !== []) {
            ModuleCapabilityRecord::query()->upsert(
                $capabilities,
                ['module_id', 'code'],
                ['name', 'description', 'is_active', 'updated_at'],
            );
        }
        $module->lockVersion = $nextLockVersion;
    }

    public function deleteIfUnreferenced(Module $module): bool
    {
        if ($this->referenceGuard->isReferenced($module->id)) {
            return false;
        }

        return ModuleRecord::query()->whereKey($module->id)->delete() === 1;
    }

    public function appendOperationalChange(OperationalControlChange $change): void
    {
        ModuleOperationalChangeRecord::query()->create([
            'id' => $change->id,
            'module_id' => $change->moduleId,
            'action' => $change->action->value,
            'actor_iam_id' => $change->actorIamId,
            'reason' => $change->reason,
            'previous_is_active' => $change->previousIsActive,
            'previous_processing_status' => $change->previousProcessingStatus->value,
            'next_is_active' => $change->nextIsActive,
            'next_processing_status' => $change->nextProcessingStatus->value,
            'occurred_at' => $change->occurredAt,
        ]);
    }

    public function paginateOperationalHistory(ModuleOperationalHistoryQueryData $query): ModuleOperationalHistoryPageData
    {
        $paginator = ModuleOperationalChangeRecord::query()
            ->where('module_id', $query->moduleId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);

        $entries = collect($paginator->items())
            ->map(fn (ModuleOperationalChangeRecord $record): ModuleOperationalChangeData => $this->hydrateChange($record)->toData())
            ->all();

        return new ModuleOperationalHistoryPageData(
            entries: $entries,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function paginate(ModuleListQueryData $query): ModulesPageData
    {
        $builder = ModuleRecord::query()
            ->with(['capabilities' => fn ($capabilities) => $capabilities->orderBy('code')])
            ->when($query->search !== null, function ($builder) use ($query): void {
                $search = '%'.mb_strtolower($query->search).'%';
                $builder->where(function ($nested) use ($search): void {
                    $nested->whereRaw('LOWER(code) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$search]);
                });
            })
            ->when($query->isActive !== null, fn ($builder) => $builder->where('is_active', $query->isActive))
            ->when($query->processingStatus !== null, fn ($builder) => $builder->where('processing_status', $query->processingStatus))
            ->orderBy('code');

        $paginator = $builder->paginate($query->perPage, ['*'], 'page', $query->page);
        $modules = collect($paginator->items())
            ->map(fn (ModuleRecord $record) => $this->hydrate($record)->toData())
            ->all();

        return new ModulesPageData(
            modules: $modules,
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    private function hydrateChange(ModuleOperationalChangeRecord $record): OperationalControlChange
    {
        return new OperationalControlChange(
            id: (string) $record->id,
            moduleId: (string) $record->module_id,
            action: OperationalControlAction::from((string) $record->action),
            actorIamId: (string) $record->actor_iam_id,
            reason: (string) $record->reason,
            previousIsActive: (bool) $record->previous_is_active,
            previousProcessingStatus: ProcessingStatus::from((string) $record->previous_processing_status),
            nextIsActive: (bool) $record->next_is_active,
            nextProcessingStatus: ProcessingStatus::from((string) $record->next_processing_status),
            occurredAt: $record->occurred_at->utc()->toISOString(),
        );
    }

    private function hydrate(ModuleRecord $record): Module
    {
        return new Module(
            id: (string) $record->id,
            code: (string) $record->code,
            name: (string) $record->name,
            description: $record->description === null ? null : (string) $record->description,
            isActive: (bool) $record->is_active,
            processingStatus: ProcessingStatus::from((string) $record->processing_status),
            lockVersion: (int) $record->lock_version,
            capabilities: $record->capabilities->map(
                static fn (ModuleCapabilityRecord $capability): ModuleCapability => new ModuleCapability(
                    id: (string) $capability->id,
                    code: (string) $capability->code,
                    name: (string) $capability->name,
                    description: $capability->description === null ? null : (string) $capability->description,
                    isActive: (bool) $capability->is_active,
                    createdAt: $capability->created_at->utc()->toISOString(),
                    updatedAt: $capability->updated_at->utc()->toISOString(),
                )
            )->all(),
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function moduleAttributes(Module $module): array
    {
        return [
            'id' => $module->id,
            'code' => $module->code,
            'name' => $module->name,
            'description' => $module->description,
            'is_active' => $module->isActive,
            'processing_status' => $module->processingStatus->value,
            'lock_version' => $module->lockVersion,
            'created_at' => $module->createdAt,
            'updated_at' => $module->updatedAt,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function capabilityAttributes(Module $module): array
    {
        return array_map(
            static fn (ModuleCapability $capability): array => [
                'id' => $capability->id,
                'module_id' => $module->id,
                'code' => $capability->code,
                'name' => $capability->name,
                'description' => $capability->description,
                'is_active' => $capability->isActive,
                'created_at' => $capability->createdAt,
                'updated_at' => $capability->updatedAt,
            ],
            $module->capabilities,
        );
    }
}
