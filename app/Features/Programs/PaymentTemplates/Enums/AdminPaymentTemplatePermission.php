<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Enums;

enum AdminPaymentTemplatePermission: string
{
    case Manage = 'ib.programs.manage';
}
