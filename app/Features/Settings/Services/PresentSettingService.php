<?php

declare(strict_types=1);

namespace App\Features\Settings\Services;

use App\Features\Settings\DTOs\SettingDefinitionData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\DTOs\SettingViewData;
use Illuminate\Contracts\Config\Repository;

final class PresentSettingService
{
    public function __construct(private readonly Repository $config) {}

    public function view(SettingDefinitionData $definition, ?SettingRecordData $record): SettingViewData
    {
        $effective = $record !== null && $record->mode === 'stored' ? $record->value : $this->config->get($definition->key);
        $redacted = $definition->sensitive || ($record?->definition->sensitive ?? false);

        return new SettingViewData($definition, $record !== null, $record?->mode ?? 'fallback', $record !== null && $record->mode === 'stored' ? 'database' : 'fallback', $record?->lock_version, $redacted ? null : $record?->value, $redacted ? null : $effective, $effective !== null && $effective !== '', $redacted);
    }

    /** @return array<string, mixed>|null */
    public function auditState(?SettingRecordData $record, bool $redact = false): ?array
    {
        if ($record === null) {
            return null;
        }
        $state = ['definition' => $record->definition->toArray(), 'mode' => $record->mode, 'lock_version' => $record->lock_version];
        if ($record->definition->sensitive || $redact) {
            $state['has_stored_value'] = $record->mode === 'stored' && $record->value !== null && $record->value !== '';
        } else {
            $state['value'] = $record->value;
        }

        return $state;
    }
}
