<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\UseCases;

use App\Features\Programs\ProgressionTemplates\Actions\TransformProgressionTemplateToDataAction;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\SharedFeatures\Clock\DomainClock;
use Illuminate\Support\Str;

final class StoreProgressionTemplateUseCase
{
    public function __construct(
        private readonly ProgressionTemplateRepositoryFactory $repositoryFactory,
        private readonly TransformProgressionTemplateToDataAction $transform,
    ) {}

    public function execute(ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $now = app(DomainClock::class)->now()->toISOString();
        $template = new ProgressionTemplate((string) Str::uuid7(), $command->name ?? '', $command->description, 1, [], $now, $now);
        $this->repositoryFactory->make()->save($template);

        return $this->transform->execute($template);
    }
}
