<?php

namespace App\Modules\Assets\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class AssetCategoryPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Asset Category';
    }
}
