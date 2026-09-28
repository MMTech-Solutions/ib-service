<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class PublishProgressionTemplateVersionUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(string $id, string $versionId, int $lockVersion): ProgressionTemplateData
    {
        return $this->catalog->publishVersion($id, $versionId, $lockVersion);
    }
}
