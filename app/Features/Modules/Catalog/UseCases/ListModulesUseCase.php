<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Contracts\Data\ModuleListQueryData;
use App\Features\Modules\Catalog\Contracts\Data\ModulesPageData;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;

final class ListModulesUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(ModuleListQueryData $query): ModulesPageData
    {
        return $this->repositoryFactory->make()->paginate($query);
    }
}
