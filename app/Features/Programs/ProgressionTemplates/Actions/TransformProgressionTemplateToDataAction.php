<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Actions;

use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateLevelData;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateVersionData;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateLevel;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;

final class TransformProgressionTemplateToDataAction
{
    public function execute(ProgressionTemplate $template): ProgressionTemplateData
    {
        return new ProgressionTemplateData(
            id: $template->id,
            name: $template->name,
            description: $template->description,
            lock_version: $template->lockVersion,
            versions: array_map(
                static fn (ProgressionTemplateVersion $version): ProgressionTemplateVersionData => new ProgressionTemplateVersionData(
                    id: $version->id,
                    version_number: $version->versionNumber,
                    status: $version->status,
                    published_at: $version->publishedAt,
                    lock_version: $version->lockVersion,
                    levels: array_map(
                        static fn (ProgressionTemplateLevel $level): ProgressionTemplateLevelData => new ProgressionTemplateLevelData(
                            distribution_level: $level->distributionLevel,
                            weight: $level->weight,
                        ),
                        $version->levels,
                    ),
                ),
                $template->versions,
            ),
            created_at: $template->createdAt,
            updated_at: $template->updatedAt,
        );
    }
}
