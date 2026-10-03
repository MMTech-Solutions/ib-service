<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\ClosedVolumeRewardActivityProviderFactory;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Contracts\Data\V1\ResolveClosedVolumeRewardActivityQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Contracts\Exceptions\VolumeRewardModuleNotOperationalException;
use App\Features\Modules\Contracts\Ports\Input\ResolveClosedVolumeRewardActivityPort;

final class ResolveClosedVolumeRewardActivityUseCase implements ResolveClosedVolumeRewardActivityPort
{
    public function __construct(
        private readonly ModuleRepositoryFactory $repositoryFactory,
        private readonly ClosedVolumeRewardActivityProviderFactory $providers,
    ) {}

    public function execute(ResolveClosedVolumeRewardActivityQueryData $query): VolumeRewardActivityData
    {
        $module = $this->repositoryFactory->make()->findById($query->module_id);
        if ($module === null) {
            throw ModuleNotFoundException::forIds([$query->module_id]);
        }
        if (! $module->isActive || $module->processingStatus->value !== 'running' || $module->code !== 'broker') {
            throw VolumeRewardModuleNotOperationalException::forCondition(
                ! $module->isActive ? 'inactive' : $module->processingStatus->value,
            );
        }

        return $this->providers->make($module->code)->fetch($query);
    }
}
