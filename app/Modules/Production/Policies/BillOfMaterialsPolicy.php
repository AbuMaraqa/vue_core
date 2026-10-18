<?php

namespace App\Modules\Production\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Recipes are plain records: listing them is enough to read one.
 */
class BillOfMaterialsPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Bill Of Materials';
    }
}
