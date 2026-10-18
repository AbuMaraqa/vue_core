<?php

namespace App\Modules\Payroll\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class PayComponentPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Pay Component';
    }
}
