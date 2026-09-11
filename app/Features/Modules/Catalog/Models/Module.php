<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Models;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityData;
use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleData;
use App\Features\Modules\Catalog\DTOs\ModuleDefinitionData;
use App\Features\Modules\Catalog\DTOs\ModuleDetailData;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;
use Closure;

final class Module
{
    /**
     * @param  list<ModuleCapability>  $capabilities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public ProcessingStatus $processingStatus,
        public int $lockVersion,
        public array $capabilities,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromDefinition(
        string $id,
        ModuleDefinitionData $definition,
        Closure $generateId,
        string $now,
    ): self {
        return new self(
            id: $id,
            code: $definition->code,
            name: $definition->name,
            description: $definition->description,
            isActive: true,
            processingStatus: ProcessingStatus::Running,
            lockVersion: 1,
            capabilities: array_map(
                static fn (ModuleCapabilityDefinitionData $capability): ModuleCapability => new ModuleCapability(
                    id: $generateId(),
                    code: $capability->code,
                    name: $capability->name,
                    description: $capability->description,
                    isActive: true,
                    createdAt: $now,
                    updatedAt: $now,
                ),
                $definition->capabilities,
            ),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @return array{changed: bool, activated: int, deactivated: int}
     */
    public function reconcileCapabilities(
        ModuleDefinitionData $definition,
        Closure $generateId,
        string $now,
    ): array {
        $definitions = [];
        foreach ($definition->capabilities as $capability) {
            $definitions[$capability->code] = $capability;
        }

        $changed = false;
        $activated = 0;
        $deactivated = 0;

        foreach ($this->capabilities as $capability) {
            $capabilityDefinition = $definitions[$capability->code] ?? null;
            if ($capabilityDefinition === null) {
                if ($capability->deactivate($now)) {
                    $changed = true;
                    $deactivated++;
                }

                continue;
            }

            $wasActive = $capability->isActive;
            if ($capability->reconcile($capabilityDefinition, $now)) {
                $changed = true;
                if (! $wasActive) {
                    $activated++;
                }
            }
            unset($definitions[$capability->code]);
        }

        foreach ($definitions as $capabilityDefinition) {
            $this->capabilities[] = new ModuleCapability(
                id: $generateId(),
                code: $capabilityDefinition->code,
                name: $capabilityDefinition->name,
                description: $capabilityDefinition->description,
                isActive: true,
                createdAt: $now,
                updatedAt: $now,
            );
            $changed = true;
            $activated++;
        }

        if ($changed) {
            $this->updatedAt = $now;
        }

        return compact('changed', 'activated', 'deactivated');
    }

    public function updateAdministrativeFields(string $name, ?string $description, string $now): bool
    {
        if ($this->name === $name && $this->description === $description) {
            return false;
        }

        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = $now;

        return true;
    }

    public function activate(string $now): bool
    {
        if ($this->isActive) {
            return false;
        }

        $this->isActive = true;
        $this->updatedAt = $now;

        return true;
    }

    public function deactivate(string $now): bool
    {
        if (! $this->isActive) {
            return false;
        }

        $this->isActive = false;
        $this->updatedAt = $now;

        return true;
    }

    public function pause(string $now): bool
    {
        if ($this->processingStatus === ProcessingStatus::Paused) {
            return false;
        }

        $this->processingStatus = ProcessingStatus::Paused;
        $this->updatedAt = $now;

        return true;
    }

    public function resume(string $now): bool
    {
        if ($this->processingStatus === ProcessingStatus::Running) {
            return false;
        }

        $this->processingStatus = ProcessingStatus::Running;
        $this->updatedAt = $now;

        return true;
    }

    public function toData(): ModuleData
    {
        usort($this->capabilities, static fn (ModuleCapability $a, ModuleCapability $b): int => $a->code <=> $b->code);

        return new ModuleData(
            id: $this->id,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            is_active: $this->isActive,
            processing_status: $this->processingStatus->value,
            lock_version: $this->lockVersion,
            capabilities: array_map(
                static fn (ModuleCapability $capability): ModuleCapabilityData => $capability->toData(),
                $this->capabilities,
            ),
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }

    public function toDetailData(): ModuleDetailData
    {
        $list = $this->toData();

        return new ModuleDetailData(
            id: $list->id,
            code: $list->code,
            name: $list->name,
            description: $list->description,
            is_active: $list->is_active,
            processing_status: $list->processing_status,
            lock_version: $list->lock_version,
            capabilities: $list->capabilities,
            created_at: $list->created_at,
            updated_at: $list->updated_at,
        );
    }
}
