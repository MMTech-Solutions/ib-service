<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\Contracts\Data\V1\ProgressionTemplateVersionIdentityData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgressionTemplateVersionPort;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;

final class ResolveProgressionTemplateVersionUseCase implements ResolveProgressionTemplateVersionPort
{
    public function __construct(private readonly ProgressionTemplateRepositoryFactory $repositories) {}

    public function execute(string $versionId): ?ProgressionTemplateVersionIdentityData
    {
        $version = $this->repositories->make()->findVersion($versionId);

        return $version === null ? null : new ProgressionTemplateVersionIdentityData($version->id, $version->templateId, $version->status);
    }
}
