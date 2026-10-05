<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\BuildProgressionTemplateLevelsAction;
use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateVersionAction;
use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\SharedFeatures\Clock\DomainClock;

final class UpdateProgressionTemplateVersionUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
        private readonly ResolveProgressionTemplateVersionAction $resolveVersion,
        private readonly BuildProgressionTemplateLevelsAction $buildLevels,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, string $versionId, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $versionId, $command): ProgressionTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $version = $this->resolveVersion->execute($template, $versionId);
            if (! $version->isDraft()) {
                throw ProgressionTemplateException::immutable($versionId);
            }

            $version->replaceLevels($this->buildLevels->execute($command->levels ?? []), app(DomainClock::class)->now()->toISOString());
            $repository->saveVersion($version, false, $command->lockVersion);

            return $this->transform->execute($template);
        });
    }
}
