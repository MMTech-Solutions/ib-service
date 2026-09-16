<?php

declare(strict_types=1);

namespace App\SharedFeatures\User;

use App\SharedFeatures\User\Context\GatewayUserConnector;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mmtech\Rbac\Auth\GatewayUser;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserContext::class, static function (Application $app): UserContext {
            $authFactory = $app->make(AuthFactory::class);
            $guard = (string) config('rbac.auth.guard', 'web');
            $gatewayUser = $authFactory->guard($guard)->user();

            if (! $gatewayUser instanceof GatewayUser) {
                throw new AuthenticationException('A gateway user is required.');
            }

            return new UserContext(new GatewayUserConnector(
                $gatewayUser,
                $app->make(PermissionCheckerInterface::class),
            ));
        });
    }
}
