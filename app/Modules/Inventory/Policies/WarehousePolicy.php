<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Inventory\Models\Warehouse;

class WarehousePolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Warehouse';
    }

    /**
     * A warehouse that has held stock stays: deactivate it instead. Lists
     * preload the check with `withExists('movements')`.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        if ($warehouse->getAttribute('movements_exists') ?? $warehouse->movements()->exists()) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
