<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum SettingPermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case View = 'settings.view';
    case Update = 'settings.update';

    public static function groupLabel(): string
    {
        return 'Settings';
    }

    public function label(): string
    {
        return match ($this) {
            self::View => 'View settings',
            self::Update => 'Update settings',
        };
    }
}
