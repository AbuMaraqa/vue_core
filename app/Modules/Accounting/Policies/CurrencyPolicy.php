<?php

namespace App\Modules\Accounting\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class CurrencyPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Currency';
    }
}
