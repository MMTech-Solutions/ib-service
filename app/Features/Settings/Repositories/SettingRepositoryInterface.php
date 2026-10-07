<?php

declare(strict_types=1);

namespace App\Features\Settings\Repositories;

use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingRecordData;
use Closure;

interface SettingRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    /** @param list<string>|null $keys @return array<string, SettingRecordData> */
    public function records(?array $keys = null): array;

    public function save(SettingRecordData $record, ?int $expectedVersion): void;

    public function delete(string $key): void;

    public function audit(SettingAuditData $audit): void;
}
