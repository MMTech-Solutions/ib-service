<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Contracts\Data\V1\ProgramProgressionConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramProgressionConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramProgressionConfigurationPort;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class ResolveProgramProgressionConfigurationUseCase implements ResolveProgramProgressionConfigurationPort
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(ResolveProgramProgressionConfigurationQueryData $query): ?ProgramProgressionConfigurationData
    {
        $occurredAt = CarbonImmutable::parse($query->occurred_at)->utc();
        $configuration = $this->connection->table('program_symbol_configurations')
            ->where('program_id', $query->program_id)
            ->where('module_id', $query->module_id)
            ->where('symbol_reference', $query->instrument_reference)
            ->where('use_for_progression', true)
            ->where('starts_at', '<=', $occurredAt)
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $occurredAt))
            ->orderByDesc('starts_at')
            ->first();

        if ($configuration === null || $configuration->plan_progression_template_version_binding_id === null) {
            return null;
        }

        $binding = $this->connection->table('plan_progression_template_version_bindings')
            ->where('id', $configuration->plan_progression_template_version_binding_id)
            ->first();
        if ($binding === null) {
            return null;
        }

        $level = $this->connection->table('progression_template_levels')
            ->where('template_version_id', $binding->template_version_id)
            ->where('distribution_level', $query->distribution_level)
            ->first();
        if ($level === null) {
            return null;
        }

        return new ProgramProgressionConfigurationData(
            program_symbol_configuration_id: (string) $configuration->id,
            plan_progression_template_version_binding_id: (string) $binding->id,
            progression_template_version_id: (string) $binding->template_version_id,
            distribution_weight: (string) $level->weight,
        );
    }
}
