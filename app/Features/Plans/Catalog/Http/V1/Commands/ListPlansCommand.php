<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Commands;

use App\Features\Plans\Catalog\DTOs\PlanListQueryData;
use App\Features\Plans\Catalog\Http\V1\Requests\ListPlansRequest;
use Spatie\LaravelData\Data;

final class ListPlansCommand extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $search,
        public readonly ?bool $isActive,
    ) {}

    public static function fromRequest(ListPlansRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            search: isset($validated['search']) ? trim((string) $validated['search']) : null,
            isActive: isset($validated['is_active']) ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOL) : null,
        );
    }

    public function toQueryData(): PlanListQueryData
    {
        return new PlanListQueryData(
            page: $this->page,
            perPage: $this->perPage,
            search: $this->search,
            isActive: $this->isActive,
        );
    }
}
