<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Create the system roles. Super admins bypass permission checks, so only
     * the admin role receives the registered permissions explicitly.
     */
    public function run(PermissionRegistry $permissions): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        Role::findByName(UserRole::Admin->value, 'web')->syncPermissions($permissions->names());
    }
}
