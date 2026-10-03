<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\RewardReadAccessData;
use App\Features\Rewards\Enums\AdminRewardPermission;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use Illuminate\Auth\Access\AuthorizationException;

final class ResolveRewardReadAccessAction
{
    public function resolve(UserContext $user, UserSurface $surface): RewardReadAccessData
    {
        if ($surface === UserSurface::CustomerApp) {
            return new RewardReadAccessData(false, $user->id());
        }
        if (! $user->can(AdminRewardPermission::Manage, UserSurface::AdminPanel)) {
            throw new AuthorizationException;
        }

        return new RewardReadAccessData(true, null);
    }
}
