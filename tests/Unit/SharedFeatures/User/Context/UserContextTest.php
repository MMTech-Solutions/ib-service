<?php

declare(strict_types=1);

namespace Tests\Unit\SharedFeatures\User\Context;

use App\Features\Modules\Catalog\Enums\AdminModulePermission;
use App\SharedFeatures\User\Context\Contracts\UserConnectorInterface;
use App\SharedFeatures\User\Context\UserContext;
use App\SharedFeatures\User\Context\UserSurface;
use PHPUnit\Framework\TestCase;

final class UserContextTest extends TestCase
{
    public function test_it_normalizes_permissions_and_requires_an_explicit_surface(): void
    {
        $connector = new class implements UserConnectorInterface
        {
            /** @var list<string>|string|null */
            public array|string|null $abilities = null;

            public ?UserSurface $surface = null;

            public function id(): string
            {
                return 'admin-id';
            }

            public function can(array|string $abilities, UserSurface $surface): bool
            {
                $this->abilities = $abilities;
                $this->surface = $surface;

                return true;
            }
        };

        $context = new UserContext($connector);

        self::assertSame('admin-id', $context->id());
        self::assertTrue($context->can(AdminModulePermission::Manage, UserSurface::AdminPanel));
        self::assertSame('ib.modules.manage', $connector->abilities);
        self::assertSame(UserSurface::AdminPanel, $connector->surface);
    }
}
