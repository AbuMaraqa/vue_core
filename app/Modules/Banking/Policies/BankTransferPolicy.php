<?php

namespace App\Modules\Banking\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Banking\Models\BankTransfer;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

/**
 * Transfers are posted when saved and only cancelled afterwards.
 */
class BankTransferPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Bank Transfer';
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, BankTransfer $transfer): bool
    {
        return $transfer->status === DocumentStatus::Posted && $this->hasPermission($user, 'Cancel');
    }
}
