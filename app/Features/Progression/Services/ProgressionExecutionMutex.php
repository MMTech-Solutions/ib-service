<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use Illuminate\Database\ConnectionInterface;
use LogicException;

final class ProgressionExecutionMutex
{
    private const int LOCK_NAMESPACE = 134756;

    private const int LOCK_RESOURCE = 240;

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function acquire(): bool
    {
        if ($this->connection->getDriverName() !== 'pgsql') {
            throw new LogicException('Progression operational commands require PostgreSQL.');
        }

        $record = $this->connection->selectOne(
            'SELECT pg_try_advisory_lock(?, ?) AS acquired',
            [self::LOCK_NAMESPACE, self::LOCK_RESOURCE],
        );

        return (bool) $record->acquired;
    }

    public function release(): void
    {
        $this->connection->selectOne(
            'SELECT pg_advisory_unlock(?, ?)',
            [self::LOCK_NAMESPACE, self::LOCK_RESOURCE],
        );
    }
}
