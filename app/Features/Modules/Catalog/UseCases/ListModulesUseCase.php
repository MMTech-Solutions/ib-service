<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\DTOs\ModulesPageData;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Http\V1\Commands\ListModulesCommand;

final class ListModulesUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(ListModulesCommand $command): ModulesPageData
    {
        return $this->repositoryFactory->make()->paginate($command->toQueryData());
    }
}
