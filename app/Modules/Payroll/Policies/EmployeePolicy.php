<?php

namespace App\Modules\Payroll\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Payroll\Models\Employee;

class EmployeePolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Employee';
    }

    /**
     * An employee who was ever paid stays: set a termination date instead.
     */
    public function delete(User $user, Employee $employee): bool
    {
        if ($employee->getAttribute('payslips_exists') ?? $employee->payslips()->exists()) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
