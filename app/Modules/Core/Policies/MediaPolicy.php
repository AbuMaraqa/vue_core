<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class MediaPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Media';
    }
}
