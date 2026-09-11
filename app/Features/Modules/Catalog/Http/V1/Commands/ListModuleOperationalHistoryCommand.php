<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryQueryData;
use App\Features\Modules\Catalog\Http\V1\Requests\ListModuleOperationalHistoryRequest;
use Spatie\LaravelData\Data;

final class ListModuleOperationalHistoryCommand extends Data
{
    public function __construct(
        public readonly string $moduleId,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    public static function fromRequest(ListModuleOperationalHistoryRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            moduleId: (string) $validated['module'],
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
        );
    }

    public function toQueryData(): ModuleOperationalHistoryQueryData
    {
        return new ModuleOperationalHistoryQueryData(
            moduleId: $this->moduleId,
            page: $this->page,
            perPage: $this->perPage,
        );
    }
}
