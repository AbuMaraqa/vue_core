<?php

use App\Models\User;
use App\Modules\Core\Enums\Permissions\RolePermission;
use App\Modules\Core\Enums\Permissions\UserPermission;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared sidebar only contains the entries the user may open', function () {
    $this->actingAs(staffMember(UserPermission::ViewAny))
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sidebar', 2)
            ->where('sidebar.0.items.0.key', 'dashboard')
            ->has('sidebar.1.items', 1)
            ->where('sidebar.1.items.0.type', 'group')
            ->where('sidebar.1.items.0.active', true)
            ->has('sidebar.1.items.0.children', 1)
            ->where('sidebar.1.items.0.children.0.url', route('admin.users.index', absolute: false))
            ->where('sidebar.1.items.0.children.0.active', true),
        );
});

test('super admins see every sidebar entry', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sidebar.1.items', 4)
            ->has('sidebar.1.items.0.children', 3),
        );
});

test('groups without visible children are hidden', function () {
    $sidebar = new Sidebar;
    $sidebar->section('main', 'Menu')->add(
        SidebarGroup::make('Roles')->add(
            SidebarItem::make('Roles')->route('admin.roles.index')->can(RolePermission::ViewAny),
        ),
    );

    $request = Request::create('/');
    $request->setUserResolver(fn () => User::factory()->create());

    expect($sidebar->resolve($request))->toBe([]);
});

test('sections and their entries are ordered by their sort value', function () {
    $sidebar = new Sidebar;
    $sidebar->section('system', 'System', sort: 20)->add(
        SidebarItem::make('Settings')->route('admin.settings.edit')->sort(2),
        SidebarItem::make('Users')->route('admin.users.index')->sort(1),
    );
    $sidebar->section('main', 'Menu', sort: 10)->add(
        SidebarItem::make('Dashboard')->route('admin.dashboard'),
    );

    $resolved = $sidebar->resolve(Request::create('/'));

    expect(array_column($resolved, 'key'))->toBe(['main', 'system'])
        ->and(array_column($resolved[1]['items'], 'key'))->toBe(['users', 'settings']);
});
