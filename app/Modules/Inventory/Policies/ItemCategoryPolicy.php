<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class ItemCategoryPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Item Category';
    }
}
