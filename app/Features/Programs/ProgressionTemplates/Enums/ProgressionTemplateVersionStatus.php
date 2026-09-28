<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Enums;

enum ProgressionTemplateVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
