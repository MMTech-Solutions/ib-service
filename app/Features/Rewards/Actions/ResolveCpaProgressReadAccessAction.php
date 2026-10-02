<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\CpaProgressReadAccessData;
use App\Features\Rewards\Enums\AdminRewardPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Auth\Access\AuthorizationException;

final class ResolveCpaProgressReadAccessAction
{
    public function resolve(UserContext $userContext, UserSurface $surface): CpaProgressReadAccessData
    {
        return match ($surface) {
            UserSurface::AdminPanel => $this->administrative($userContext),
            UserSurface::CustomerApp => new CpaProgressReadAccessData(false, $userContext->id()),
        };
    }

    private function administrative(UserContext $userContext): CpaProgressReadAccessData
    {
        if (! $userContext->can(AdminRewardPermission::Manage, UserSurface::AdminPanel)) {
            throw new AuthorizationException;
        }

        return new CpaProgressReadAccessData(true, null);
    }
}
