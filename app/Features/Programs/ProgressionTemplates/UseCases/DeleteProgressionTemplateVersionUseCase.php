<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class DeleteProgressionTemplateVersionUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(string $id, string $versionId, int $lockVersion): void
    {
        $this->catalog->deleteVersion($id, $versionId, $lockVersion);
    }
}
