<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\BuildProgressionTemplateLevelsAction;
use App\Features\Programs\ProgressionTemplates\Actions\ResolveProgressionTemplateAction;
use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StoreProgressionTemplateVersionUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly ResolveProgressionTemplateAction $resolveTemplate,
        private readonly BuildProgressionTemplateLevelsAction $buildLevels,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    public function execute(string $id, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->repositoryFactory->make();

        return $repository->transaction(function () use ($repository, $id, $command): ProgressionTemplateData {
            $template = $this->resolveTemplate->execute($id);
            $now = CarbonImmutable::now('UTC')->toISOString();
            $version = new ProgressionTemplateVersion(
                (string) Str::uuid7(),
                $template->id,
                $template->nextVersionNumber(),
                'draft',
                null,
                1,
                $this->buildLevels->execute($command->levels ?? []),
                $now,
                $now,
            );
            $repository->saveVersion($version, true);

            return $this->transform->execute($this->resolveTemplate->execute($id));
        });
    }
}
