<?php

namespace App\Modules\Production\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\ProductionOrder;

/**
 * Drafts are edited, deleted and started; orders in progress are completed;
 * any started order can be stepped back (`Production Order Reverse`).
 */
class ProductionOrderPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Production Order';
    }

    public function update(User $user, ProductionOrder $order): bool
    {
        return $order->status === ProductionOrderStatus::Draft && $this->hasPermission($user, 'Update');
    }

    public function delete(User $user, ProductionOrder $order): bool
    {
        return $order->status === ProductionOrderStatus::Draft && $this->hasPermission($user, 'Delete');
    }

    public function start(User $user, ProductionOrder $order): bool
    {
        return $order->status === ProductionOrderStatus::Draft && $this->hasPermission($user, 'Start');
    }

    public function complete(User $user, ProductionOrder $order): bool
    {
        return $order->status === ProductionOrderStatus::InProgress && $this->hasPermission($user, 'Complete');
    }

    public function reverse(User $user, ProductionOrder $order): bool
    {
        return in_array($order->status, [ProductionOrderStatus::InProgress, ProductionOrderStatus::Completed], true)
            && $this->hasPermission($user, 'Reverse');
    }
}
