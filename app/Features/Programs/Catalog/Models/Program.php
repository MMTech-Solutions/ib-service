<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Models;

use App\Features\Programs\Catalog\DTOs\ProgramData;
use App\Features\Programs\Catalog\DTOs\ProgramDetailData;
use Closure;

final class Program
{
    /**
     * @param  list<ProgramModuleSelection>  $selections
     */
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public readonly string $code,
        public string $name,
        public ?string $description,
        public int $position,
        public int $lockVersion,
        public array $selections,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * @param  list<string>  $moduleIds
     */
    public static function create(
        string $id,
        string $planId,
        string $code,
        string $name,
        ?string $description,
        int $position,
        array $moduleIds,
        Closure $generateId,
        string $now,
    ): self {
        $program = new self(
            id: $id,
            planId: $planId,
            code: $code,
            name: $name,
            description: $description,
            position: $position,
            lockVersion: 1,
            selections: [],
            createdAt: $now,
            updatedAt: $now,
        );
        $program->replaceSelections($moduleIds, $generateId, $now);

        return $program;
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

    /**
     * @param  list<string>  $moduleIds
     */
    public function replaceSelections(array $moduleIds, Closure $generateId, string $now): bool
    {
        $normalized = array_values(array_unique($moduleIds));
        $current = $this->moduleIds();
        sort($current);
        $sorted = $normalized;
        sort($sorted);

        if ($current === $sorted) {
            return false;
        }

        $this->selections = array_map(
            fn (string $moduleId): ProgramModuleSelection => new ProgramModuleSelection(
                id: $generateId(),
                programId: $this->id,
                moduleId: $moduleId,
                createdAt: $now,
            ),
            $normalized,
        );
        $this->updatedAt = $now;

        return true;
    }

    public function assignPosition(int $position, string $now): bool
    {
        if ($this->position === $position) {
            return false;
        }

        $this->position = $position;
        $this->updatedAt = $now;

        return true;
    }

    /** @return list<string> */
    public function moduleIds(): array
    {
        return array_map(
            static fn (ProgramModuleSelection $selection): string => $selection->moduleId,
            $this->selections,
        );
    }

    public function toData(): ProgramData
    {
        return new ProgramData(
            id: $this->id,
            plan_id: $this->planId,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            position: $this->position,
            lock_version: $this->lockVersion,
            module_ids: $this->moduleIds(),
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }

    public function toDetailData(): ProgramDetailData
    {
        $list = $this->toData();

        return new ProgramDetailData(
            id: $list->id,
            plan_id: $list->plan_id,
            code: $list->code,
            name: $list->name,
            description: $list->description,
            position: $list->position,
            lock_version: $list->lock_version,
            module_ids: $list->module_ids,
            created_at: $list->created_at,
            updated_at: $list->updated_at,
        );
    }
}
