<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

test('users without admin access are forbidden from the dashboard', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'));

    $response->assertForbidden();
});

test('staff members can visit the dashboard', function () {
    User::factory()->count(2)->create();

    $response = $this->actingAs(staffMember())->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->where('stats.users', 3)
        ->where('stats.newUsers', 3)
        ->has('recentUsers.data', 3)
        ->missing('registrations')
        ->loadDeferredProps(fn (Assert $reload) => $reload->has('registrations', 12)),
    );
});

test('super admins can visit the dashboard without explicit permissions', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'));

    $response->assertOk();
});
