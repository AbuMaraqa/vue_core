<?php

use App\Modules\Core\Enums\UserRole;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('the permissions are created with their module and given to the super admin role', function () {
    $this->seed(PermissionSeeder::class);

    $permission = Permission::findByName('User Create', 'web');

    expect($permission->module_name)->toBe('User Management')
        ->and(Role::findByName(UserRole::SuperAdmin->value, 'web')->permissions)->toHaveCount(Permission::query()->count());

    foreach (UserRole::cases() as $role) {
        expect(Role::query()->where('name', $role->value)->exists())->toBeTrue();
    }
});

test('seeding again keeps the permissions roles already have', function () {
    $this->seed(PermissionSeeder::class);
    $editor = Role::findOrCreate('Editor', 'web')->givePermissionTo('User View List');

    $this->seed(PermissionSeeder::class);

    expect($editor->fresh()->hasPermissionTo('User View List'))->toBeTrue();
});

test('permissions removed from the list are deleted', function () {
    Permission::create(['name' => 'Legacy Report View', 'guard_name' => 'web', 'module_name' => 'Reports']);

    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->where('name', 'Legacy Report View')->exists())->toBeFalse();
});
