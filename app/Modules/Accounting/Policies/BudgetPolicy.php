<?php

namespace App\Modules\Accounting\Policies;

use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class BudgetPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Budget';
    }
}
