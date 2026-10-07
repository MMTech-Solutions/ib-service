<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Services\CertifyProviderConnectionService;
use App\Features\Modules\Contracts\Data\V1\ConnectionCertificationData;
use App\Features\Modules\Contracts\Ports\Input\CertifyProviderConnectionPort;

final class CertifyProviderConnectionUseCase implements CertifyProviderConnectionPort
{
    public function __construct(private readonly CertifyProviderConnectionService $certifier) {}

    public function execute(string $provider): ConnectionCertificationData
    {
        return $this->certifier->certify($provider);
    }
}
