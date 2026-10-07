<?php

declare(strict_types=1);

namespace App\Features\Settings\UseCases;

use App\Features\Modules\Contracts\Data\V1\ConnectionCertificationData;
use App\Features\Modules\Contracts\Ports\Input\CertifyProviderConnectionPort;
use App\Features\Settings\Http\V1\Commands\CertifySettingsConnectionCommand;

final class CertifySettingsConnectionUseCase
{
    public function __construct(private readonly CertifyProviderConnectionPort $certifier) {}

    public function execute(CertifySettingsConnectionCommand $command): ConnectionCertificationData
    {
        return $this->certifier->execute($command->provider);
    }
}
