<?php

namespace App\Modules\Accounting\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class CostCenterPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Cost Center';
    }
}
