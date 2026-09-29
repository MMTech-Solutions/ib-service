<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\InstrumentCatalogSourceFactory;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Contracts\Exceptions\ModuleInactiveException;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;

final class ListInstrumentCatalogUseCase implements ListInstrumentCatalogPort
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly InstrumentCatalogSourceFactory $sourceFactory,
    ) {}

    public function execute(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData
    {
        $module = $this->repositoryFactory->make()->findById($query->module_id);
        if ($module === null) {
            throw ModuleNotFoundException::forIds([$query->module_id]);
        }
        if (! $module->isActive) {
            throw ModuleInactiveException::forIds([$module->id]);
        }

        return $this->sourceFactory->make($module->id, $module->code)->list($query);
    }
}
