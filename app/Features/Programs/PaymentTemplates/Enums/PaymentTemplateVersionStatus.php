<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Enums;

enum PaymentTemplateVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
