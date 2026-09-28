<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Actions;

use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;

final class ResolveProgressionTemplateAction
{
    public function __construct(private readonly ProgressionTemplateRepositoryFactory $repositoryFactory) {}

    public function execute(string $id): ProgressionTemplate
    {
        return $this->repositoryFactory->make()->find($id) ?? throw ProgressionTemplateException::notFound($id);
    }
}
