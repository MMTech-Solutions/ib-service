<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Models;

use App\Features\Modules\Catalog\Contracts\Data\ModuleCapabilityData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleCapabilityDefinitionData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleData;
use App\Features\Modules\Catalog\Contracts\Data\ModuleDefinitionData;
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

    public function deactivate(string $now): bool
    {
        if (! $this->isActive) {
            return false;
        }

        $this->isActive = false;
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
            capabilities: array_map(
                static fn (ModuleCapability $capability): ModuleCapabilityData => $capability->toData(),
                $this->capabilities,
            ),
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }
}
