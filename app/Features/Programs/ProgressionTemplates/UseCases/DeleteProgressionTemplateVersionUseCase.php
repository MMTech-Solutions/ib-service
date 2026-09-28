<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateVersionAction;
use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;

final class DeleteProgressionTemplateVersionUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
        private readonly ResolveProgressionTemplateVersionAction $resolveVersion,
    ) {}

    public function execute(string $id, string $versionId, int $lockVersion): void
    {
        $template = $this->resolveTemplate->execute($id);
        $version = $this->resolveVersion->execute($template, $versionId);
        if (! $version->isDraft()) {
            throw ProgressionTemplateException::immutable($versionId);
        }

        $this->repositoryFactory->make()->deleteVersion($version, $lockVersion);
    }
}
