<?php

declare(strict_types=1);

namespace App\SharedFeatures\User\Context;

enum UserSurface: string
{
    case AdminPanel = 'admin_panel';
    case CustomerApp = 'customer_app';
}
