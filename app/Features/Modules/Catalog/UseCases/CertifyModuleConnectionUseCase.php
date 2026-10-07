<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Exceptions\ModuleNotFoundException;
use App\Features\Modules\Catalog\Factories\ModuleRepositoryFactory;
use App\Features\Modules\Catalog\Http\V1\Commands\CertifyModuleConnectionCommand;
use App\Features\Modules\Catalog\Services\CertifyProviderConnectionService;
use App\Features\Modules\Contracts\Data\V1\ConnectionCertificationData;

final class CertifyModuleConnectionUseCase
{
    public function __construct(private readonly ModuleRepositoryFactory $repositories, private readonly CertifyProviderConnectionService $certifier) {}

    public function execute(CertifyModuleConnectionCommand $command): ConnectionCertificationData
    {
        $module = $this->repositories->make()->findById($command->module_id) ?? throw ModuleNotFoundException::forId($command->module_id);

        return $this->certifier->certify($module->code, $module->id);
    }
}
