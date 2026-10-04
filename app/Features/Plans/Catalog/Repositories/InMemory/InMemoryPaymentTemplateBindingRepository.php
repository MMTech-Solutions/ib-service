<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\InMemory;

use App\Features\Plans\Catalog\Contracts\Repositories\PaymentTemplateBindingRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingPageData;
use App\Features\Plans\Catalog\DTOs\StorePaymentTemplateBindingResultData;

final class InMemoryPaymentTemplateBindingRepository implements PaymentTemplateBindingRepositoryInterface
{
    /** @var array<string, PaymentTemplateBindingData> */
    private array $bindings = [];

    public function resolve(string $planId, string $bindingId): ?string
    {
        return $this->find($planId, $bindingId)?->template_version_id;
    }

    public function find(string $planId, string $bindingId): ?PaymentTemplateBindingData
    {
        $binding = $this->bindings[$bindingId] ?? null;

        return $binding?->plan_id === $planId ? $binding : null;
    }

    public function paginate(string $planId, int $page, int $perPage): PaymentTemplateBindingPageData
    {
        $items = array_values(array_filter($this->bindings, static fn (PaymentTemplateBindingData $binding): bool => $binding->plan_id === $planId));
        usort($items, static fn (PaymentTemplateBindingData $a, PaymentTemplateBindingData $b): int => [$a->created_at, $a->id] <=> [$b->created_at, $b->id]);

        return new PaymentTemplateBindingPageData(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page);
    }

    public function createOrFind(PaymentTemplateBindingData $binding): StorePaymentTemplateBindingResultData
    {
        foreach ($this->bindings as $existing) {
            if ($existing->plan_id === $binding->plan_id && $existing->template_version_id === $binding->template_version_id) {
                return new StorePaymentTemplateBindingResultData($existing, false);
            }
        }
        $this->bindings[$binding->id] = $binding;

        return new StorePaymentTemplateBindingResultData($binding, true);
    }
}
