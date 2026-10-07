<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Services\ModuleDefinitionRegistry;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ResolveVolumeRewardModulesPort;

final class ResolveVolumeRewardModulesUseCase implements ResolveVolumeRewardModulesPort
{
    public function __construct(private readonly ModuleRepositoryFactory $repositories, private readonly ModuleDefinitionRegistry $registry) {}

    /** @return list<ModuleSummaryData> */
    public function execute(?string $moduleId = null): array
    {
        $result = [];
        foreach ($this->registry->definitions() as $definition) {
            foreach ($definition->capabilities as $capability) {
                if ($capability->code !== 'closed_trading_volume' || $capability->event_subscriptions === []) {
                    continue;
                }
                $module = $this->repositories->make()->findByCode($definition->code);
                if ($module === null || ($moduleId !== null && $module->id !== $moduleId)) {
                    continue;
                }
                $activeCapability = false;
                foreach ($module->capabilities as $stored) {
                    if ($stored->code === $capability->code && $stored->isActive) {
                        $activeCapability = true;
                    }
                }
                $result[] = new ModuleSummaryData($module->id, $module->code, $module->name, $module->isActive && $activeCapability, $module->processingStatus->value);
            }
        }

        return $result;
    }
}
