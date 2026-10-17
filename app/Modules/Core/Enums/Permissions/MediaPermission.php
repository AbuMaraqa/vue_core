<?php

namespace App\Modules\Core\Enums\Permissions;

use App\Modules\Core\Concerns\DefinesPermissions;
use App\Modules\Core\Contracts\PermissionGroup;

enum MediaPermission: string implements PermissionGroup
{
    use DefinesPermissions;

    case ViewAny = 'media.view-any';
    case Create = 'media.create';
    case Delete = 'media.delete';

    public static function groupLabel(): string
    {
        return 'Media Library';
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewAny => 'View the media library',
            self::Create => 'Upload media',
            self::Delete => 'Delete media',
        };
    }
}
