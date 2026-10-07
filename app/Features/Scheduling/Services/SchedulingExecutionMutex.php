<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use Illuminate\Database\ConnectionInterface;
use LogicException;

final class SchedulingExecutionMutex
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function acquire(string $code): bool
    {
        if ($this->connection->getDriverName() !== 'pgsql') {
            throw new LogicException('Scheduling execution requires PostgreSQL.');
        }

        return (bool) $this->connection->selectOne('SELECT pg_try_advisory_lock(?, hashtext(?)) AS acquired', [734212, $code])->acquired;
    }

    public function release(string $code): void
    {
        $this->connection->selectOne('SELECT pg_advisory_unlock(?, hashtext(?))', [734212, $code]);
    }
}
