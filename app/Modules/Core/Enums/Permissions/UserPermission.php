<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum UserPermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case ViewAny = 'users.view-any';
    case Create = 'users.create';
    case Update = 'users.update';
    case Delete = 'users.delete';

    public static function groupLabel(): string
    {
        return 'Users';
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewAny => 'View users',
            self::Create => 'Create users',
            self::Update => 'Update users',
            self::Delete => 'Delete users',
        };
    }
}
