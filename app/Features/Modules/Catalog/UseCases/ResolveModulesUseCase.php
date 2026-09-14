<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Exceptions\ModuleInactiveException;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;

final class ResolveModulesUseCase implements ResolveModulesPort
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function findByIds(array $ids): array
    {
        $uniqueIds = array_values(array_unique($ids));
        if ($uniqueIds === []) {
            return [];
        }

        $repository = $this->repositoryFactory->make();
        $summaries = [];
        $missing = [];

        foreach ($uniqueIds as $id) {
            $module = $repository->findById($id);
            if ($module === null) {
                $missing[] = $id;

                continue;
            }

            $summaries[] = new ModuleSummaryData(
                id: $module->id,
                code: $module->code,
                name: $module->name,
                is_active: $module->isActive,
                processing_status: $module->processingStatus->value,
            );
        }

        if ($missing !== []) {
            throw ModuleNotFoundException::forIds($missing);
        }

        return $summaries;
    }

    public function assertSelectable(array $ids): array
    {
        $modules = $this->findByIds($ids);
        $inactive = [];
        foreach ($modules as $module) {
            if (! $module->is_active) {
                $inactive[] = $module->id;
            }
        }

        if ($inactive !== []) {
            throw ModuleInactiveException::forIds($inactive);
        }

        return $modules;
    }
}
