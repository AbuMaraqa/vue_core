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
     * Nobody manages an account with more access than their own, and staff
     * change their own account from their profile, not here: otherwise
     * `User Update` would let them give themselves any role.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->is($model) && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->canManage($model)) {
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

        if (! $user->canManage($model)) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
