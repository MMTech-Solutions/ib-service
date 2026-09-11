<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\DTOs\ModuleDetailData;
use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Http\V1\Commands\ShowModuleCommand;

final class ShowModuleUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(ShowModuleCommand $command): ModuleDetailData
    {
        $module = $this->repositoryFactory->make()->findById($command->moduleId);
        if ($module === null) {
            throw ModuleNotFoundException::forId($command->moduleId);
        }

        return $module->toDetailData();
    }
}
