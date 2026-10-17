<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum DashboardPermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case Access = 'dashboard.access';

    public static function groupLabel(): string
    {
        return 'Dashboard';
    }

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access the admin panel',
        };
    }
}
