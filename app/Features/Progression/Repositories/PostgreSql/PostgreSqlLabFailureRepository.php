<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class PostgreSqlLabFailureRepository
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function arm(string $id, string $contextId, string $operation, string $stage, string $selectorType, string $selectorId): void
    {
        $existing = $this->connection->table('progression_lab_failures')->where('id', $id)->first();
        $values = ['context_id' => $contextId, 'operation' => $operation, 'stage' => $stage, 'selector_type' => $selectorType, 'selector_id' => $selectorId];
        if ($existing !== null) {
            foreach ($values as $key => $value) {
                if ($value !== $existing->$key) {
                    throw new \RuntimeException('Failure ID already identifies another configuration.');
                }
            }

            return;
        }
        $this->connection->table('progression_lab_failures')->insert(['id' => $id, ...$values, 'consumed_at' => null]);
    }

    public function consume(string $contextId, string $operation, string $stage, string $subscriptionId, string $resultId): bool
    {
        if ($this->connection->transactionLevel() !== 0) {
            throw new \RuntimeException('Lab failure consumption requires an independent committed operation.');
        }
        $query = $this->connection->table('progression_lab_failures')->where('context_id', $contextId)
            ->where('operation', $operation)->where('stage', $stage)->whereNull('consumed_at')
            ->where(function ($query) use ($subscriptionId, $resultId): void {
                $query->where(fn ($query) => $query->where('selector_type', 'subscription')->where('selector_id', $subscriptionId))
                    ->orWhere(fn ($query) => $query->where('selector_type', 'result')->where('selector_id', $resultId));
            });
        $id = (clone $query)->orderBy('id')->value('id');

        return $id !== null && $this->connection->table('progression_lab_failures')->where('id', $id)->whereNull('consumed_at')->update(['consumed_at' => CarbonImmutable::now('UTC')]) === 1;
    }
}
