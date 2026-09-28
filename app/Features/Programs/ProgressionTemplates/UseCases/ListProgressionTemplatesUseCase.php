<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;

final class ListProgressionTemplatesUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    /** @return list<ProgressionTemplateData> */
    public function execute(): array
    {
        return array_map(
            fn (ProgressionTemplate $template): ProgressionTemplateData => $this->transform->execute($template),
            $this->repositoryFactory->make()->all(),
        );
    }
}
