<?php

declare(strict_types=1);

namespace App\SharedFeatures\User\Context;

use App\SharedFeatures\User\Context\Contracts\UserConnectorInterface;
use Mmtech\Rbac\Auth\GatewayUser;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;

final readonly class GatewayUserConnector implements UserConnectorInterface
{
    public function __construct(
        private GatewayUser $user,
        private PermissionCheckerInterface $permissionChecker,
    ) {}

    public function id(): string
    {
        return $this->user->getAuthIdentifier();
    }

    public function can(array|string $abilities, UserSurface $surface): bool
    {
        foreach ((array) $abilities as $ability) {
            if (! $this->permissionChecker->userCan($this->id(), $ability, $surface->value)) {
                return false;
            }
        }

        return true;
    }
}
