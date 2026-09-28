<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class UpdateProgressionTemplateVersionUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(string $id, string $versionId, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        return $this->catalog->updateVersion($id, $versionId, $command);
    }
}
