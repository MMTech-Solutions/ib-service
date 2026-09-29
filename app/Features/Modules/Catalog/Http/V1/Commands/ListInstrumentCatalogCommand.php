<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Commands;

use App\Features\Modules\Catalog\Http\V1\Requests\ListInstrumentCatalogRequest;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use Spatie\LaravelData\Data;

final class ListInstrumentCatalogCommand extends Data
{
    /** @param array<string, string> $filters */
    public function __construct(
        public readonly string $moduleId,
        public readonly string $type,
        public readonly array $filters,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    public static function fromRequest(ListInstrumentCatalogRequest $request): self
    {
        $validated = $request->validated();
        $filters = array_filter(
            $request->safe()->only(['search', 'platform', 'trading_server', 'server_group', 'security', 'symbol']),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        return new self(
            moduleId: (string) $validated['module'],
            type: (string) $validated['type'],
            filters: $filters,
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
        );
    }

    public function toQueryData(): ListInstrumentCatalogQueryData
    {
        return new ListInstrumentCatalogQueryData($this->moduleId, $this->type, $this->filters, $this->page, $this->perPage);
    }
}
