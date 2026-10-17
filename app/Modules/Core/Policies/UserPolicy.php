<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Enums\Permissions\UserPermission;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermission::ViewAny);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermission::Create);
    }

    /**
     * Only super admins may manage other super admins.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->can(UserPermission::Update);
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

        return $user->can(UserPermission::Delete);
    }
}
