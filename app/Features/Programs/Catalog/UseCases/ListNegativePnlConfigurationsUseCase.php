<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\UseCases;

use App\Features\Programs\Catalog\Factories\NegativePnlConfigurationRepositoryFactory;
use App\Features\Programs\Contracts\Ports\Input\ListNegativePnlConfigurationsPort;

final class ListNegativePnlConfigurationsUseCase implements ListNegativePnlConfigurationsPort
{
    public function __construct(private readonly NegativePnlConfigurationRepositoryFactory $repositories) {}

    public function execute(?string $afterId, int $limit, ?string $programId = null, ?string $from = null, ?string $until = null): array
    {
        return $this->repositories->make()->list($afterId, max(1, min($limit, 1000)), $programId, $from, $until);
    }
}
