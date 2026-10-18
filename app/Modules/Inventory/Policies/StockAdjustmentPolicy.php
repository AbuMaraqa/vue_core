<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Inventory\Models\StockAdjustment;

/**
 * Adjustments are posted when saved; they are never edited or deleted, only
 * cancelled, which reverses their effect.
 */
class StockAdjustmentPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Stock Adjustment';
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, StockAdjustment $adjustment): bool
    {
        return $adjustment->status === DocumentStatus::Posted && $this->hasPermission($user, 'Cancel');
    }
}
