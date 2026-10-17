<?php

use App\Models\User;
use App\Modules\Core\Enums\Permissions\RolePermission;
use App\Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Role;

test('the super admin role can never be edited', function () {
    $role = Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

    expect(User::factory()->superAdmin()->create()->can('update', $role))->toBeFalse();
});

test('system roles can be edited but never deleted', function (UserRole $systemRole) {
    $role = Role::findOrCreate($systemRole->value, 'web');
    $superAdmin = User::factory()->superAdmin()->create();

    expect($superAdmin->can('update', $role))->toBeTrue()
        ->and($superAdmin->can('delete', $role))->toBeFalse();
})->with([UserRole::Admin, UserRole::Customer]);

test('custom roles require the matching permission', function (string $ability, RolePermission $permission) {
    $role = Role::findOrCreate('Editor', 'web');

    expect(User::factory()->withPermissions($permission)->create()->can($ability, $role))->toBeTrue()
        ->and(User::factory()->create()->can($ability, $role))->toBeFalse();
})->with([
    'update' => ['update', RolePermission::Update],
    'delete' => ['delete', RolePermission::Delete],
]);
