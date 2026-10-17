<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single place where the permissions of the system are defined.
 *
 * To add a permission, add it to the list below and run:
 * php artisan db:seed --class=PermissionSeeder
 *
 * Permissions removed from the list are deleted, while the roles keep every
 * permission that still exists.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            [
                'name' => 'Dashboard View',
                'guard_name' => 'web',
                'module_name' => 'Dashboard Management',
            ],

            // User
            [
                'name' => 'User View List',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Create',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Update',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Delete',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Export',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],
            [
                'name' => 'User Import',
                'guard_name' => 'web',
                'module_name' => 'User Management',
            ],

            // Roles And Permissions
            [
                'name' => 'Roles And Permissions View List',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Create',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Update',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],
            [
                'name' => 'Roles And Permissions Delete',
                'guard_name' => 'web',
                'module_name' => 'Roles And Permissions Management',
            ],

            // Media
            [
                'name' => 'Media View List',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],
            [
                'name' => 'Media Create',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],
            [
                'name' => 'Media Delete',
                'guard_name' => 'web',
                'module_name' => 'Media Management',
            ],

            // Setting
            [
                'name' => 'Setting View',
                'guard_name' => 'web',
                'module_name' => 'Settings Management',
            ],
            [
                'name' => 'Setting Update',
                'guard_name' => 'web',
                'module_name' => 'Settings Management',
            ],

            // Activity Log
            [
                'name' => 'Activity Log View List',
                'guard_name' => 'web',
                'module_name' => 'Activity Log Management',
            ],

            // Login History
            [
                'name' => 'Login History View List',
                'guard_name' => 'web',
                'module_name' => 'Login History Management',
            ],
            [
                'name' => 'Login History Export',
                'guard_name' => 'web',
                'module_name' => 'Login History Management',
            ],

            // Backup
            [
                'name' => 'Backup View List',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Create',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Download',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
            [
                'name' => 'Backup Delete',
                'guard_name' => 'web',
                'module_name' => 'Backup Management',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                ['module_name' => $permission['module_name']],
            );
        }

        $deleted = Permission::query()->whereNotIn('name', array_column($permissions, 'name'))->delete();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        Role::findByName(UserRole::SuperAdmin->value, 'web')->syncPermissions(array_column($permissions, 'name'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info(sprintf('%d permissions synced, %d removed, all assigned to %s.', count($permissions), $deleted, UserRole::SuperAdmin->value));
    }
}
