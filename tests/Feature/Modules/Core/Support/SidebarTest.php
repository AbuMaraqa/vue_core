<?php

use App\Models\User;
use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Support\Sidebar\Sidebar;
use App\Modules\Core\Support\Sidebar\SidebarGroup;
use App\Modules\Core\Support\Sidebar\SidebarItem;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared sidebar only contains the entries the user may open', function () {
    $this->actingAs(staffMember('User View List'))
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

test('in the full mode super admins see every sidebar entry', function () {
    app(AppearanceSettings::class)->fill(['simple_mode' => false])->save();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('sidebar', function ($sections): bool {
            $system = collect($sections)->firstWhere('key', 'system');
            $items = collect($system['items'] ?? []);

            return $items->pluck('key')->intersect(['users-management', 'media-library', 'activity-log', 'settings'])->count() === 4
                && count($items->firstWhere('key', 'users-management')['children'] ?? []) === 3;
        }));
});

test('groups without visible children are hidden', function () {
    $sidebar = new Sidebar;
    $sidebar->section('main', 'Menu')->add(
        SidebarGroup::make('Roles')->add(
            SidebarItem::make('Roles')->route('admin.roles.index')->can('Roles And Permissions View List'),
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

describe('simple mode', function () {
    function simpleModeSidebar(): Sidebar
    {
        $sidebar = new Sidebar;
        $sidebar->section('main', 'Menu')->add(
            SidebarItem::make('Dashboard')->simpleTitle('Home')->route('admin.dashboard'),
            SidebarItem::make('Activity Log')->route('admin.activity-log.index')->advanced(),
            SidebarGroup::make('General Ledger')->advanced()->add(
                SidebarItem::make('Users')->route('admin.users.index'),
            ),
        );

        return $sidebar;
    }

    test('advanced entries are hidden and plain titles are used', function () {
        app(AppearanceSettings::class)->fill(['simple_mode' => true])->save();

        $items = simpleModeSidebar()->resolve(Request::create('/'))[0]['items'];

        expect(array_column($items, 'title'))->toBe(['Home'])
            ->and(array_column($items, 'key'))->toBe(['dashboard']);
    });

    test('the full mode shows every entry with its own title', function () {
        app(AppearanceSettings::class)->fill(['simple_mode' => false])->save();

        $items = simpleModeSidebar()->resolve(Request::create('/'))[0]['items'];

        expect(array_column($items, 'title'))->toBe(['Dashboard', 'Activity Log', 'General Ledger']);
    });

    test('sections use their plain title only in simple mode', function (bool $simple, string $title) {
        app(AppearanceSettings::class)->fill(['simple_mode' => $simple])->save();

        $sidebar = new Sidebar;
        $sidebar->section('accounting', 'Accounting')->simpleTitle('My daily work')->add(
            SidebarItem::make('Dashboard')->route('admin.dashboard'),
        );

        expect($sidebar->resolve(Request::create('/'))[0]['title'])->toBe($title);
    })->with([
        'simple mode' => [true, 'My daily work'],
        'full mode' => [false, 'Accounting'],
    ]);
});
