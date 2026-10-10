<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Contracts\Data\V1\ProgressionResultReferenceData;
use App\Features\Progression\Contracts\Data\V1\ResolveProgressionResultReferencesQueryData;
use App\Features\Progression\Contracts\Ports\Input\ResolveProgressionResultReferencesPort;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;

final class ResolveProgressionResultReferencesUseCase implements ResolveProgressionResultReferencesPort
{
    public function __construct(private readonly ProgressionRunRepositoryFactory $repositoryFactory) {}

    /** @return list<ProgressionResultReferenceData> */
    public function execute(ResolveProgressionResultReferencesQueryData $query): array
    {
        return $this->repositoryFactory->make()->resultReferences($query);
    }
}
