<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Models;

use App\Features\Modules\Catalog\DTOs\ModuleCapabilityData;
use App\Features\Modules\Catalog\DTOs\ModuleCapabilityDefinitionData;

final class ModuleCapability
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    public function reconcile(ModuleCapabilityDefinitionData $definition, string $updatedAt): bool
    {
        $changed = $this->name !== $definition->name
            || $this->description !== $definition->description
            || ! $this->isActive;

        if ($changed) {
            $this->name = $definition->name;
            $this->description = $definition->description;
            $this->isActive = true;
            $this->updatedAt = $updatedAt;
        }

        return $changed;
    }

    public function deactivate(string $updatedAt): bool
    {
        if (! $this->isActive) {
            return false;
        }

        $this->isActive = false;
        $this->updatedAt = $updatedAt;

        return true;
    }

    public function toData(): ModuleCapabilityData
    {
        return new ModuleCapabilityData(
            id: $this->id,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            is_active: $this->isActive,
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }
}
