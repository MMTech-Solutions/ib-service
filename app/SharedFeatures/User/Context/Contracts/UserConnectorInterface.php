<?php

declare(strict_types=1);

namespace App\SharedFeatures\User\Context\Contracts;

use App\SharedFeatures\User\Context\UserSurface;

interface UserConnectorInterface
{
    public function id(): string;

    /** @param list<string>|string $abilities */
    public function can(array|string $abilities, UserSurface $surface): bool;
}
