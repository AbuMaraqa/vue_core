<?php

namespace App\Modules\Accounting\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class TaxPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Tax';
    }
}
