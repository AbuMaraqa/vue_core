<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum RolePermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case ViewAny = 'roles.view-any';
    case Create = 'roles.create';
    case Update = 'roles.update';
    case Delete = 'roles.delete';

    public static function groupLabel(): string
    {
        return 'Roles & Permissions';
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewAny => 'View roles',
            self::Create => 'Create roles',
            self::Update => 'Update roles',
            self::Delete => 'Delete roles',
        };
    }
}
