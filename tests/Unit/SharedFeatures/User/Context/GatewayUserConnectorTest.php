<?php

declare(strict_types=1);

namespace Tests\Unit\SharedFeatures\User\Context;

use App\SharedFeatures\User\Context\GatewayUserConnector;
use App\SharedFeatures\User\Context\UserSurface;
use Mmtech\Rbac\Auth\GatewayUser;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class GatewayUserConnectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_checks_the_authenticated_subject_on_the_explicit_surface(): void
    {
        $permissionChecker = Mockery::mock(PermissionCheckerInterface::class);
        $permissionChecker->shouldReceive('userCan')
            ->once()
            ->with('admin-id', 'ib.modules.manage', 'admin_panel')
            ->andReturnTrue();

        $connector = new GatewayUserConnector(new GatewayUser('admin-id'), $permissionChecker);

        self::assertSame('admin-id', $connector->id());
        self::assertTrue($connector->can('ib.modules.manage', UserSurface::AdminPanel));
    }
}
