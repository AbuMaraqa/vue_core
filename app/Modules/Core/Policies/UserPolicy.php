<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;

class UserPolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'User';
    }

    /**
     * Only super admins may manage other super admins.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->hasPermission($user, 'Update');
    }

    /**
     * Users can never delete their own account from the admin panel.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
