<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Settings\DTOs\SettingAuditData;
use App\Features\Settings\DTOs\SettingRecordData;
use App\Features\Settings\DTOs\SyncSettingsResultData;
use App\Features\Settings\Factories\SettingRepositoryFactory;
use App\Features\Settings\Services\PresentSettingService;
use App\Features\Settings\Services\SettingDefinitionRegistry;
use App\Features\Settings\Services\SettingValueValidator;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Contracts\Config\Repository;

final class SyncSettingsUseCase
{
    public function __construct(private readonly SettingRepositoryFactory $repositories, private readonly SettingDefinitionRegistry $registry, private readonly SettingValueValidator $validator, private readonly PresentSettingService $presenter, private readonly Repository $config, private readonly DomainClock $clock) {}

    public function execute(bool $dryRun = false, string $actor = 'cli:settings:sync'): SyncSettingsResultData
    {
        $definitions = $this->registry->all();
        $repository = $this->repositories->make();

        return $repository->transaction(function () use ($repository, $definitions, $dryRun, $actor): SyncSettingsResultData {
            $existing = $repository->records();
            $pending = [];
            $created = $updated = $unchanged = 0;
            $now = $this->clock->now()->toISOString();
            foreach ($definitions as $definition) {
                $before = $existing[$definition->key] ?? null;
                $value = $before?->mode === 'stored' ? $before->value : $this->config->get($definition->key);
                $this->validator->validate($definition, $value);
                if ($before !== null && $before->definition->toArray() === $definition->toArray()) {
                    $unchanged++;
                    unset($existing[$definition->key]);

                    continue;
                }
                $after = new SettingRecordData($definition, $before?->mode === 'fallback' ? null : $value, $before?->mode ?? 'stored', ($before?->lock_version ?? 0) + 1, $before?->created_at ?? $now, $now);
                $pending[] = [$before, $after];
                if ($before === null) {
                    $created++;
                } else {
                    $updated++;
                }
                unset($existing[$definition->key]);
            }
            if (! $dryRun) {
                foreach ($pending as [$before, $after]) {
                    $repository->save($after, $before?->lock_version);
                    $redact = $after->definition->sensitive || ($before?->definition->sensitive ?? false);
                    $repository->audit(new SettingAuditData($after->definition->key, $actor, $before === null ? 'sync_create' : 'sync_update', 'Code catalog synchronization', $this->presenter->auditState($before, $redact), $this->presenter->auditState($after, $redact), $now));
                }
                foreach ($existing as $key => $before) {
                    $repository->delete($key);
                    $repository->audit(new SettingAuditData($key, $actor, 'sync_delete', 'Removed from code catalog', $this->presenter->auditState($before), null, $now));
                }
            }

            return new SyncSettingsResultData($created, $updated, count($existing), $unchanged, $dryRun);
        });
    }
}
