<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Enums\Permissions\RolePermission;
use App\Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(RolePermission::ViewAny);
    }

    public function create(User $user): bool
    {
        return $user->can(RolePermission::Create);
    }

    /**
     * The super admin role always holds every permission, so it is never edited.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            return false;
        }

        return $user->can(RolePermission::Update);
    }

    /**
     * Roles the system relies on can never be deleted.
     */
    public function delete(User $user, Role $role): bool
    {
        if (UserRole::isProtected($role->name)) {
            return false;
        }

        return $user->can(RolePermission::Delete);
    }
}
