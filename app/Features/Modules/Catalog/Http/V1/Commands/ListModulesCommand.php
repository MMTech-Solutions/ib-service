<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\DTOs\ModuleListQueryData;
use App\Features\Modules\Catalog\Http\V1\Requests\ListModulesRequest;
use Spatie\LaravelData\Data;

final class ListModulesCommand extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $search,
        public readonly ?bool $isActive,
        public readonly ?string $processingStatus,
    ) {}

    public static function fromRequest(ListModulesRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            search: isset($validated['search']) ? trim((string) $validated['search']) : null,
            isActive: isset($validated['is_active']) ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOL) : null,
            processingStatus: isset($validated['processing_status']) ? (string) $validated['processing_status'] : null,
        );
    }

    public function toQueryData(): ModuleListQueryData
    {
        return new ModuleListQueryData(
            page: $this->page,
            perPage: $this->perPage,
            search: $this->search,
            isActive: $this->isActive,
            processingStatus: $this->processingStatus,
        );
    }
}
