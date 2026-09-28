<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class ShowProgressionTemplateUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(string $id): ProgressionTemplateData
    {
        return $this->catalog->show($id);
    }
}
