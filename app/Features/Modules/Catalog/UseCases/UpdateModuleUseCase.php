<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\DTOs\ModuleDetailData;
use App\Features\Modules\Catalog\Exceptions\ModuleConcurrencyException;
use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Http\V1\Commands\UpdateModuleCommand;
use Carbon\CarbonImmutable;

final class UpdateModuleUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositoryFactory) {}

    public function execute(UpdateModuleCommand $command): ModuleDetailData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $command): ModuleDetailData {
            $module = $repository->findById($command->moduleId);
            if ($module === null) {
                throw ModuleNotFoundException::forId($command->moduleId);
            }

            if ($module->lockVersion !== $command->lockVersion) {
                throw ModuleConcurrencyException::forModule($command->moduleId);
            }

            $now = CarbonImmutable::now('UTC')->toISOString();
            $nextName = $command->hasName ? (string) $command->name : $module->name;
            $nextDescription = $command->hasDescription ? $command->description : $module->description;
            if (! $module->updateAdministrativeFields($nextName, $nextDescription, $now)) {
                return $module->toDetailData();
            }

            $repository->update($module, $command->lockVersion);

            return $module->toDetailData();
        });
    }
}
