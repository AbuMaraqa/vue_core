<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Inventory\Models\Item;

class ItemPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Item';
    }

    /**
     * An item that has moved in stock stays, so its history remains intact:
     * deactivate it instead. Lists preload the check with
     * `withExists('movements')`.
     */
    public function delete(User $user, Item $item): bool
    {
        if ($item->movements_exists ?? $item->movements()->exists()) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
