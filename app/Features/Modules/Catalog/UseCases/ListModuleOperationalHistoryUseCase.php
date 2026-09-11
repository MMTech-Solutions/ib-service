<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\DTOs\ModuleOperationalHistoryPageData;
use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Http\V1\Commands\ListModuleOperationalHistoryCommand;

final class ListModuleOperationalHistoryUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(ListModuleOperationalHistoryCommand $command): ModuleOperationalHistoryPageData
    {
        $query = $command->toQueryData();
        $repository = $this->repositoryFactory->make();
        if ($repository->findById($query->moduleId) === null) {
            throw ModuleNotFoundException::forId($query->moduleId);
        }

        return $repository->paginateOperationalHistory($query);
    }
}
