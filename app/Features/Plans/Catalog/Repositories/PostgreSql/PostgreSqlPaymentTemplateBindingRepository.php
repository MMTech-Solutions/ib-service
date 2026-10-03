<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Repositories\PostgreSql;

use App\Features\Plans\Catalog\Contracts\Repositories\PaymentTemplateBindingRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlPaymentTemplateBindingRepository implements PaymentTemplateBindingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(string $planId, string $bindingId): ?string
    {
        $value = $this->connection->table('plan_payment_template_version_bindings')->where('plan_id', $planId)->where('id', $bindingId)->value('template_version_id');

        return $value === null ? null : (string) $value;
    }
}
