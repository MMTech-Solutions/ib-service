<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;

final class DeleteProgressionTemplateUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
    ) {}

    public function execute(string $id, int $lockVersion): void
    {
        $this->repositoryFactory->make()->delete($this->resolveTemplate->execute($id), $lockVersion);
    }
}
