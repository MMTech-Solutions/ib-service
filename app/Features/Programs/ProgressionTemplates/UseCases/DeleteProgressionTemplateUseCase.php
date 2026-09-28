<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Services\ProgressionTemplateCatalogService;

final class DeleteProgressionTemplateUseCase
{
    public function __construct(private readonly ProgressionTemplateCatalogService $catalog) {}

    public function execute(string $id, int $lockVersion): void
    {
        $this->catalog->delete($id, $lockVersion);
    }
}
