<?php

use App\Modules\Core\Enums\UserRole;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('staff members without the permission are forbidden', function () {
    $this->actingAs(staffMember())
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

test('the create screen lists the permissions grouped by module', function () {
    $this->actingAs(staffMember('Roles And Permissions Create'))
        ->get(route('admin.roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Create')
            ->where('permissionGroups', function ($groups): bool {
                $userGroup = collect($groups)->firstWhere('module', 'User Management');

                return $userGroup['permissions'] === ['User View List', 'User Create', 'User Update', 'User Delete', 'User Export', 'User Import'];
            }),
        );
});

test('a role is created with its permissions and recorded in the activity log', function () {
    $this->actingAs(staffMember('Roles And Permissions Create'))
        ->post(route('admin.roles.store'), [
            'name' => 'Editor',
            'permissions' => ['User View List', 'User Update'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Editor', 'web');

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['User View List', 'User Update'])
        ->and(Activity::query()->where('log_name', 'roles')->where('event', 'created')->exists())->toBeTrue();
});

test('unknown permissions are rejected', function () {
    $this->actingAs(staffMember('Roles And Permissions Create'))
        ->post(route('admin.roles.store'), [
            'name' => 'Editor',
            'permissions' => ['orders.refund'],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('system roles keep their name when updated', function () {
    $role = Role::findOrCreate(UserRole::Admin->value, 'web');

    $this->actingAs(staffMember('Roles And Permissions Update'))
        ->put(route('admin.roles.update', $role), [
            'name' => 'Renamed',
            'permissions' => [],
        ])
        ->assertSessionHasErrors('name');

    expect($role->fresh()->name)->toBe(UserRole::Admin->value);
});

test('the permissions of a role are replaced on update', function () {
    $role = Role::findOrCreate('Editor', 'web')->givePermissionTo('User Delete');

    $this->actingAs(staffMember('Roles And Permissions Update'))
        ->put(route('admin.roles.update', $role), [
            'name' => 'Content Editor',
            'permissions' => ['User View List'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('Content Editor')
        ->and($role->permissions->pluck('name')->all())->toBe(['User View List']);
});

test('custom roles can be deleted', function () {
    $role = Role::findOrCreate('Editor', 'web');

    $this->actingAs(staffMember('Roles And Permissions Delete'))
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    $this->assertModelMissing($role);
});

test('system roles cannot be deleted', function () {
    $role = Role::findOrCreate(UserRole::Customer->value, 'web');

    $this->actingAs(staffMember('Roles And Permissions Delete'))
        ->delete(route('admin.roles.destroy', $role))
        ->assertForbidden();

    $this->assertModelExists($role);
});
