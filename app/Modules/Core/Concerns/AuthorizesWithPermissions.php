<?php

namespace App\Modules\Core\Concerns;

use App\Models\User;

/**
 * Answer the standard policy abilities with the permissions named after the
 * module, as defined in the PermissionSeeder: for the prefix `User`, `viewAny`
 * checks `User View List`, `create` checks `User Create`, and so on.
 *
 * A policy can still define its own method for an ability to add rules, and
 * call `hasPermission()` to keep the permission check.
 */
trait AuthorizesWithPermissions
{
    /**
     * The first part of the permission names, e.g. `User` for `User Create`.
     */
    abstract protected function permissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'View List');
    }

    public function view(User $user): bool
    {
        return $this->hasPermission($user, 'View');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'Create');
    }

    public function update(User $user): bool
    {
        return $this->hasPermission($user, 'Update');
    }

    public function delete(User $user): bool
    {
        return $this->hasPermission($user, 'Delete');
    }

    /**
     * Check the permission of the given action, e.g. `Update` for `User Update`.
     */
    protected function hasPermission(User $user, string $action): bool
    {
        return $user->can("{$this->permissionPrefix()} {$action}");
    }
}
