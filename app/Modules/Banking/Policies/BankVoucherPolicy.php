<?php

namespace App\Modules\Banking\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Banking\Models\BankVoucher;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Vouchers are posted when saved and only cancelled afterwards.
 */
class BankVoucherPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Bank Voucher';
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, BankVoucher $voucher): bool
    {
        return $voucher->status === DocumentStatus::Posted && $this->hasPermission($user, 'Cancel');
    }
}
