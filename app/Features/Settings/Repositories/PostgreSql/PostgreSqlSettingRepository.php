<?php

declare(strict_types=1);

namespace App\Features\Settings\Repositories\PostgreSql;

use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\Exceptions\SettingException;
use App\Features\Settings\Repositories\SettingRepositoryInterface;
use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class PostgreSqlSettingRepository implements SettingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly Encrypter $encrypter) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($callback): mixed {
            $this->connection->select('SELECT pg_advisory_xact_lock(71931007)');

            return $callback();
        });
    }

    public function records(?array $keys = null): array
    {
        $rows = $this->connection->table('settings')->when($keys !== null, fn (Builder $query): Builder => $query->whereIn('key', $keys))->orderBy('key')->get();
        $records = [];
        foreach ($rows as $row) {
            $definition = SettingDefinitionData::from(json_decode($row->definition, true, 512, JSON_THROW_ON_ERROR));
            $encoded = $row->value;
            if ($encoded !== null && $definition->sensitive) {
                $encoded = $this->encrypter->decryptString($encoded);
            }
            $records[$row->key] = new SettingRecordData($definition, $encoded === null ? null : json_decode($encoded, true, 512, JSON_THROW_ON_ERROR), $row->mode, $row->lock_version, $row->created_at, $row->updated_at);
        }

        return $records;
    }

    public function save(SettingRecordData $record, ?int $expectedVersion): void
    {
        $encoded = $record->mode === 'fallback' ? null : json_encode($record->value, JSON_THROW_ON_ERROR);
        if ($encoded !== null && $record->definition->sensitive) {
            $encoded = $this->encrypter->encryptString($encoded);
        }
        $attributes = [
            'definition' => json_encode($record->definition->toArray(), JSON_THROW_ON_ERROR),
            'value' => $encoded,
            'mode' => $record->mode,
            'lock_version' => $record->lock_version,
            'updated_at' => $record->updated_at,
        ];
        if ($expectedVersion === null) {
            try {
                $this->connection->table('settings')->insert(['key' => $record->definition->key, 'created_at' => $record->created_at, ...$attributes]);
            } catch (UniqueConstraintViolationException) {
                throw SettingException::conflict();
            }
        } elseif ($this->connection->table('settings')->where('key', $record->definition->key)->where('lock_version', $expectedVersion)->update($attributes) !== 1) {
            throw SettingException::conflict();
        }
    }

    public function delete(string $key): void
    {
        $this->connection->table('settings')->where('key', $key)->delete();
    }

    public function audit(SettingAuditData $audit): void
    {
        $this->connection->table('setting_audits')->insert([
            'id' => (string) Str::uuid7(),
            'key' => $audit->key,
            'actor' => $audit->actor,
            'action' => $audit->action,
            'reason' => $audit->reason,
            'before' => $audit->before === null ? null : json_encode($audit->before, JSON_THROW_ON_ERROR),
            'after' => $audit->after === null ? null : json_encode($audit->after, JSON_THROW_ON_ERROR),
            'occurred_at' => $audit->occurred_at,
        ]);
    }
}
