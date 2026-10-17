<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum ActivityLogPermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case ViewAny = 'activity-log.view-any';

    public static function groupLabel(): string
    {
        return 'Activity Log';
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewAny => 'View activity log',
        };
    }
}
