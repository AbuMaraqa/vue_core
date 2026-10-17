<?php

use App\Models\User;
use App\Modules\Core\Enums\Permissions\UserPermission;
use App\Modules\Core\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

describe('index', function () {
    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    });

    test('users can be searched and filtered by role', function () {
        Role::findOrCreate('Editor', 'web');
        User::factory()->withRole('Editor')->create(['name' => 'Sara Editor']);
        User::factory()->create(['name' => 'Sara Customer']);
        User::factory()->withRole('Editor')->create(['name' => 'Omar Editor']);

        $this->actingAs(staffMember(UserPermission::ViewAny))
            ->get(route('admin.users.index', ['filter' => ['search' => 'Sara', 'role' => 'Editor']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Sara Editor')
                ->where('filters.search', 'Sara'),
            );
    });
});

describe('store', function () {
    test('a user is created with the selected roles and a verified email', function () {
        Role::findOrCreate('Editor', 'web');

        $this->actingAs(staffMember(UserPermission::Create))
            ->post(route('admin.users.store'), [
                'name' => 'New Member',
                'email' => 'member@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['Editor'],
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'member@example.com')->firstOrFail();

        expect($user->hasRole('Editor'))->toBeTrue()
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and(Activity::query()->where('subject_id', $user->id)->where('event', 'created')->exists())->toBeTrue();
    });

    test('the required fields are validated', function () {
        $this->actingAs(staffMember(UserPermission::Create))
            ->post(route('admin.users.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    });

    test('only super admins can grant the super admin role', function () {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        $this->actingAs(staffMember(UserPermission::Create))
            ->post(route('admin.users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [UserRole::SuperAdmin->value],
            ])
            ->assertSessionHasErrors('roles.0');

        expect(User::query()->where('email', 'sneaky@example.com')->exists())->toBeFalse();
    });
});

describe('update', function () {
    test('the password is kept when left blank', function () {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs(staffMember(UserPermission::Update))
            ->put(route('admin.users.update', $user), [
                'name' => 'Renamed',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'roles' => [],
            ])
            ->assertRedirect(route('admin.users.index'));

        expect($user->fresh())
            ->name->toBe('Renamed')
            ->password->toBe($originalPassword);
    });

    test('the password and roles are changed when provided', function () {
        Role::findOrCreate('Editor', 'web');
        $user = User::factory()->withRole('Editor')->create();

        $this->actingAs(staffMember(UserPermission::Update))
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
                'roles' => [],
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        expect(Hash::check('new-password', $user->password))->toBeTrue()
            ->and($user->roles)->toBeEmpty();
    });

    test('staff members cannot edit a super admin', function () {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs(staffMember(UserPermission::Update))
            ->get(route('admin.users.edit', $superAdmin))
            ->assertForbidden();
    });
});

describe('destroy', function () {
    test('a user is deleted', function () {
        $user = User::factory()->create();

        $this->actingAs(staffMember(UserPermission::Delete))
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($user);
    });

    test('users cannot delete their own account from the admin panel', function () {
        $staffMember = staffMember(UserPermission::Delete);

        $this->actingAs($staffMember)
            ->delete(route('admin.users.destroy', $staffMember))
            ->assertForbidden();

        $this->assertModelExists($staffMember);
    });
});
