<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Repositories\PostgreSql;

use App\Features\Programs\Catalog\Contracts\Repositories\ProgramVolumeRewardConfigurationRepositoryInterface;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardDistributionLimitQueryData;
use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardDistributionLimitData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardProgramConfigurationData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PostgreSqlProgramVolumeRewardConfigurationRepository implements ProgramVolumeRewardConfigurationRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function replace(string $programId, string $mode, string $at): array
    {
        return $this->connection->transaction(function () use ($programId, $mode, $at): array {
            $current = $this->connection->table('program_volume_reward_configurations')->where('program_id', $programId)->whereNull('ends_at')->lockForUpdate()->first();
            if ($current !== null && $current->mode === $mode) {
                return (array) $current;
            }
            if ($current !== null) {
                $this->connection->table('program_volume_reward_configurations')->where('id', $current->id)->update(['ends_at' => $at, 'updated_at' => $at]);
            }
            $record = ['id' => (string) Str::uuid7(), 'program_id' => $programId, 'mode' => $mode, 'starts_at' => $at, 'ends_at' => null, 'created_at' => $at, 'updated_at' => $at];
            $this->connection->table('program_volume_reward_configurations')->insert($record);

            return $record;
        });
    }

    public function resolveDistributionLimit(ResolveVolumeRewardDistributionLimitQueryData $query): ?VolumeRewardDistributionLimitData
    {
        $from = CarbonImmutable::parse($query->occurred_from)->utc();
        $until = CarbonImmutable::parse($query->occurred_until)->utc();
        if (! $from->lt($until) || ! in_array($query->channel, ['event', 'periodic'], true)) {
            return null;
        }

        $configurations = $this->connection->table('program_symbol_configurations')
            ->where('module_id', $query->module_id)
            ->where('use_for_volume_reward', true)
            ->where('starts_at', '<', $until)
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $from))
            ->whereNotNull('plan_payment_template_version_binding_id')
            ->get();

        $instruments = [];
        $maxLevel = null;
        foreach ($configurations as $configuration) {
            if (! $this->modeMatchesDuring((string) $configuration->program_id, $query->channel, $from, $until)) {
                continue;
            }

            $binding = $this->connection->table('plan_payment_template_version_bindings')
                ->where('id', $configuration->plan_payment_template_version_binding_id)
                ->first();
            if ($binding === null) {
                continue;
            }

            $level = $this->connection->table('payment_template_levels')
                ->where('template_version_id', $binding->template_version_id)
                ->max('distribution_level');
            if ($level === null) {
                continue;
            }

            $instruments[(string) $configuration->symbol_reference] = true;
            $maxLevel = max($maxLevel ?? 0, (int) $level);
        }

        if ($maxLevel === null || $instruments === []) {
            return null;
        }

        $references = array_keys($instruments);
        sort($references);

        return new VolumeRewardDistributionLimitData($references, $maxLevel);
    }

    public function resolveProgramConfiguration(ResolveVolumeRewardProgramConfigurationQueryData $query): ?VolumeRewardProgramConfigurationData
    {
        if (! in_array($query->channel, ['event', 'periodic'], true) || $query->distribution_level < 0) {
            return null;
        }

        $at = CarbonImmutable::parse($query->occurred_at)->utc();
        $mode = $this->modeAt($query->program_id, $at);
        if (! $this->modeAccepts($mode['mode'], $query->channel)) {
            return null;
        }

        $configuration = $this->connection->table('program_symbol_configurations')
            ->where('program_id', $query->program_id)
            ->where('module_id', $query->module_id)
            ->where('symbol_reference', $query->instrument_reference)
            ->where('use_for_volume_reward', true)
            ->where('starts_at', '<=', $at)
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->orderByDesc('starts_at')
            ->first();
        if ($configuration === null || $configuration->plan_payment_template_version_binding_id === null) {
            return null;
        }

        $binding = $this->connection->table('plan_payment_template_version_bindings')
            ->where('id', $configuration->plan_payment_template_version_binding_id)
            ->first();
        if ($binding === null) {
            return null;
        }

        $level = $this->connection->table('payment_template_levels')
            ->where('template_version_id', $binding->template_version_id)
            ->where('distribution_level', $query->distribution_level)
            ->first();
        if ($level === null || ! in_array($configuration->commission_type, ['fixed', 'percentage'], true)) {
            return null;
        }

        return new VolumeRewardProgramConfigurationData(
            program_volume_configuration_id: $mode['id'],
            program_symbol_configuration_id: (string) $configuration->id,
            mode: $mode['mode'],
            plan_payment_template_version_binding_id: (string) $binding->id,
            payment_template_version_id: (string) $binding->template_version_id,
            commission_type: (string) $configuration->commission_type,
            commission_value: (string) $configuration->commission_value,
            distribution_level: $query->distribution_level,
            template_level_rate: (string) $level->rate,
            configured_currency_code: (string) $configuration->currency_code,
        );
    }

    /** @return array{id: ?string, mode: string} */
    private function modeAt(string $programId, CarbonImmutable $at): array
    {
        $configuration = $this->connection->table('program_volume_reward_configurations')
            ->where('program_id', $programId)
            ->where('starts_at', '<=', $at)
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->orderByDesc('starts_at')
            ->first();

        return $configuration === null
            ? ['id' => null, 'mode' => 'periodic']
            : ['id' => (string) $configuration->id, 'mode' => (string) $configuration->mode];
    }

    private function modeMatchesDuring(string $programId, string $channel, CarbonImmutable $from, CarbonImmutable $until): bool
    {
        if ($this->modeAccepts($this->modeAt($programId, $from)['mode'], $channel)) {
            return true;
        }

        return $this->connection->table('program_volume_reward_configurations')
            ->where('program_id', $programId)
            ->where('starts_at', '>', $from)
            ->where('starts_at', '<', $until)
            ->whereIn('mode', $channel === 'event' ? ['event', 'both'] : ['periodic', 'both'])
            ->exists();
    }

    private function modeAccepts(string $mode, string $channel): bool
    {
        return $mode === 'both' || $mode === $channel;
    }
}
