<?php

declare(strict_types=1);

namespace App\Features\Settings\Repositories\InMemory;

use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use Closure;
use Throwable;

final class InMemorySettingRepository implements SettingRepositoryInterface
{
    /** @var array<string, SettingRecordData> */
    private array $stored = [];

    /** @var list<SettingAuditData> */
    private array $audits = [];

    public function transaction(Closure $callback): mixed
    {
        $snapshot = $this->stored;
        $audits = $this->audits;
        try {
            return $callback();
        } catch (Throwable $error) {
            $this->stored = $snapshot;
            $this->audits = $audits;
            throw $error;
        }
    }

    public function records(?array $keys = null): array
    {
        return $keys === null ? $this->stored : array_intersect_key($this->stored, array_flip($keys));
    }

    public function save(SettingRecordData $record, ?int $expectedVersion): void
    {
        $key = $record->definition->key;
        if (($this->stored[$key]->lock_version ?? null) !== $expectedVersion) {
            throw SettingException::conflict();
        }
        $this->stored[$key] = $record;
    }

    public function delete(string $key): void
    {
        unset($this->stored[$key]);
    }

    public function audit(SettingAuditData $audit): void
    {
        $this->audits[] = $audit;
    }
}
