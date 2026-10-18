<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class UnitPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Unit';
    }
}
