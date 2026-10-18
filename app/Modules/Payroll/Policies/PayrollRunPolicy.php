<?php

namespace App\Modules\Payroll\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;

/**
 * Drafts are adjusted and posted; posted payrolls are paid (simple mode does
 * both at once); any step can be taken back (`Payroll Run Cancel`).
 */
class PayrollRunPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Payroll Run';
    }

    public function update(User $user, PayrollRun $run): bool
    {
        return $run->status === PayrollRunStatus::Draft && $this->hasPermission($user, 'Update');
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function post(User $user, PayrollRun $run): bool
    {
        return $run->status === PayrollRunStatus::Draft && $this->hasPermission($user, 'Post');
    }

    public function pay(User $user, PayrollRun $run): bool
    {
        return $run->status === PayrollRunStatus::Posted && $this->hasPermission($user, 'Pay');
    }

    /**
     * Post (when still a draft) and pay in one step: needs every permission
     * the separate steps need.
     */
    public function postAndPay(User $user, PayrollRun $run): bool
    {
        return match ($run->status) {
            PayrollRunStatus::Draft => $this->hasPermission($user, 'Post') && $this->hasPermission($user, 'Pay'),
            PayrollRunStatus::Posted => $this->hasPermission($user, 'Pay'),
            default => false,
        };
    }

    public function reverse(User $user, PayrollRun $run): bool
    {
        return $run->status !== PayrollRunStatus::Cancelled && $this->hasPermission($user, 'Cancel');
    }
}
