<?php

namespace App\Modules\Core\Policies;

use App\Models\User;
use App\Modules\Core\Concerns\AuthorizesWithPermissions;
use App\Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use AuthorizesWithPermissions;

    protected function permissionPrefix(): string
    {
        return 'Roles And Permissions';
    }

    /**
     * The super admin role always holds every permission, so it is never edited.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            return false;
        }

        return $this->hasPermission($user, 'Update');
    }

    /**
     * Roles the system relies on can never be deleted.
     */
    public function delete(User $user, Role $role): bool
    {
        if (UserRole::isProtected($role->name)) {
            return false;
        }

        return $this->hasPermission($user, 'Delete');
    }
}
