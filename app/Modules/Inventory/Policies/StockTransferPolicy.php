<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Inventory\Models\StockTransfer;

/**
 * Transfers are posted when saved; they are never edited or deleted, only
 * cancelled, which sends the stock back.
 */
class StockTransferPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Stock Transfer';
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $transfer->status === DocumentStatus::Posted && $this->hasPermission($user, 'Cancel');
    }
}
