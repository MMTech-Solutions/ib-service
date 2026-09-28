<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Actions;

use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;

final class ResolveProgressionTemplateVersionAction
{
    public function execute(ProgressionTemplate $template, string $versionId): ProgressionTemplateVersion
    {
        foreach ($template->versions as $version) {
            if ($version->id === $versionId) {
                return $version;
            }
        }

        throw ProgressionTemplateException::versionNotFound($versionId);
    }
}
