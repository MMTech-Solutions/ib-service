<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Mmtech\Rbac\Authorization\Contracts\PermissionCheckerInterface;
use Mmtech\Rbac\Authorization\RbacPermissionChecker;

trait SettingsGateway
{
    private const SETTINGS_ACTOR = '01993ac2-8750-73fd-b102-ba24fb06d8be';

    /** @param list<string> $permissions */
    private function gateway(array $permissions = ['ib.settings.read', 'ib.settings.manage', 'ib.settings.secrets.manage', 'ib.modules.manage']): void
    {
        config()->set('rbac.gateway.internal_secret', 'settings-gateway-test');
        config()->set('rbac.fallback.enabled', false);
        app()->forgetInstance(PermissionCheckerInterface::class);
        app()->forgetInstance(RbacPermissionChecker::class);
        DB::table('rbac_user_permission_snapshots')->updateOrInsert(['sub' => self::SETTINGS_ACTOR, 'surface' => 'admin_panel'], [
            'message_key' => 'snapshot-settings-'.self::SETTINGS_ACTOR, 'rev' => 1, 'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'roles' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $userinfo = rtrim(strtr(base64_encode(json_encode(['sub' => self::SETTINGS_ACTOR], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $this->withHeaders(['X-Internal-Gateway' => 'settings-gateway-test', 'X-Userinfo' => $userinfo]);
    }
}
