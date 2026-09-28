<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class ListProgressionTemplatesUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(): array
    {
        return $this->catalog->index();
    }
}
