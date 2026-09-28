<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;

final class ShowProgressionTemplateUseCase
{
    public function __construct(
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    public function execute(string $id): ProgressionTemplateData
    {
        return $this->transform->execute($this->resolveTemplate->execute($id));
    }
}
