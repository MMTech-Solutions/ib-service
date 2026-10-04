<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql;

use App\Features\Plans\Catalog\Contracts\Repositories\PaymentTemplateBindingRepositoryInterface;
use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingPageData;
use App\Features\Plans\Catalog\DTOs\StorePaymentTemplateBindingResultData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlPaymentTemplateBindingRepository implements PaymentTemplateBindingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(string $planId, string $bindingId): ?string
    {
        return $this->find($planId, $bindingId)?->template_version_id;
    }

    public function find(string $planId, string $bindingId): ?PaymentTemplateBindingData
    {
        $row = $this->connection->table('plan_payment_template_version_bindings')->where('plan_id', $planId)->where('id', $bindingId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function paginate(string $planId, int $page, int $perPage): PaymentTemplateBindingPageData
    {
        $rows = $this->connection->table('plan_payment_template_version_bindings')->where('plan_id', $planId)->orderBy('created_at')->orderBy('id')->paginate($perPage, ['*'], 'page', $page);

        return new PaymentTemplateBindingPageData(array_map($this->hydrate(...), $rows->items()), $rows->total(), $perPage, $page);
    }

    public function createOrFind(PaymentTemplateBindingData $binding): StorePaymentTemplateBindingResultData
    {
        $created = $this->connection->table('plan_payment_template_version_bindings')->insertOrIgnore($binding->toArray()) === 1;
        $row = $this->connection->table('plan_payment_template_version_bindings')->where('plan_id', $binding->plan_id)->where('template_version_id', $binding->template_version_id)->firstOrFail();

        return new StorePaymentTemplateBindingResultData($this->hydrate($row), $created);
    }

    private function hydrate(object $row): PaymentTemplateBindingData
    {
        return new PaymentTemplateBindingData((string) $row->id, (string) $row->plan_id, (string) $row->template_version_id, CarbonImmutable::parse($row->created_at)->utc()->toISOString());
    }
}
