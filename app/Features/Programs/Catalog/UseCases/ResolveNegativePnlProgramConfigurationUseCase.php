<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\NegativePnlConfigurationRepositoryFactory;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveNegativePnlProgramConfigurationPort;

final class ResolveNegativePnlProgramConfigurationUseCase implements ResolveNegativePnlProgramConfigurationPort
{
    public function __construct(private readonly NegativePnlConfigurationRepositoryFactory $repositories) {}

    public function execute(ResolveNegativePnlProgramConfigurationQueryData $query): ?NegativePnlProgramConfigurationData
    {
        return $this->repositories->make()->resolve($query);
    }
}
