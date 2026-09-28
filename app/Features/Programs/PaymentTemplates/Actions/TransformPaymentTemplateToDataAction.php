<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Actions;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateLevelData;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateVersionData;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateLevel;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;

final class TransformPaymentTemplateToDataAction
{
    public function execute(PaymentTemplate $template): PaymentTemplateData
    {
        return new PaymentTemplateData(
            id: $template->id,
            name: $template->name,
            description: $template->description,
            lock_version: $template->lockVersion,
            versions: array_map(
                static fn (PaymentTemplateVersion $version): PaymentTemplateVersionData => new PaymentTemplateVersionData(
                    id: $version->id,
                    version_number: $version->versionNumber,
                    status: $version->status,
                    published_at: $version->publishedAt,
                    lock_version: $version->lockVersion,
                    levels: array_map(
                        static fn (PaymentTemplateLevel $level): PaymentTemplateLevelData => new PaymentTemplateLevelData(
                            distribution_level: $level->distributionLevel,
                            rate: $level->rate,
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
