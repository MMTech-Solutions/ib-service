<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\SharedFeatures\Clock\DomainClock;

final class UpdateProgressionTemplateUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $command): ProgressionTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $template->updateDetails($command->name, $command->description, app(DomainClock::class)->now()->toISOString());
            $repository->save($template, $command->lockVersion);

            return $this->transform->execute($template);
        });
    }
}
