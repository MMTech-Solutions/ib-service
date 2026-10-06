<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\UseCases;

use App\Features\Modules\Catalog\Factories\CpaEvidenceProviderFactory;
use App\Features\Modules\Contracts\Ports\Input\ResolveCpaEvidenceCapabilityPort;

final class ResolveCpaEvidenceCapabilityUseCase implements ResolveCpaEvidenceCapabilityPort
{
    public function __construct(private readonly CpaEvidenceProviderFactory $providers) {}

    public function execute(string $moduleCode): bool
    {
        return $this->providers->supports($moduleCode);
    }
}
