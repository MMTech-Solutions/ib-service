<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\InMemory;

use App\Features\Plans\Catalog\Contracts\Repositories\ProgressionTemplateBindingRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingPageData;
use App\Features\Plans\Catalog\DTOs\StoreProgressionTemplateBindingResultData;

final class InMemoryProgressionTemplateBindingRepository implements ProgressionTemplateBindingRepositoryInterface
{
    /** @var array<string, ProgressionTemplateBindingData> */
    private array $bindings = [];

    public function resolve(string $planId, string $bindingId): ?string
    {
        return $this->find($planId, $bindingId)?->template_version_id;
    }

    public function find(string $planId, string $bindingId): ?ProgressionTemplateBindingData
    {
        $binding = $this->bindings[$bindingId] ?? null;

        return $binding?->plan_id === $planId ? $binding : null;
    }

    public function paginate(string $planId, int $page, int $perPage): ProgressionTemplateBindingPageData
    {
        $items = array_values(array_filter($this->bindings, static fn (ProgressionTemplateBindingData $binding): bool => $binding->plan_id === $planId));
        usort($items, static fn (ProgressionTemplateBindingData $a, ProgressionTemplateBindingData $b): int => [$a->created_at, $a->id] <=> [$b->created_at, $b->id]);

        return new ProgressionTemplateBindingPageData(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page);
    }

    public function createOrFind(ProgressionTemplateBindingData $binding): StoreProgressionTemplateBindingResultData
    {
        foreach ($this->bindings as $existing) {
            if ($existing->plan_id === $binding->plan_id && $existing->template_version_id === $binding->template_version_id) {
                return new StoreProgressionTemplateBindingResultData($existing, false);
            }
        }
        $this->bindings[$binding->id] = $binding;

        return new StoreProgressionTemplateBindingResultData($binding, true);
    }
}
