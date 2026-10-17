<?php

use App\Models\User;
use App\Modules\Core\Enums\Permissions\ActivityLogPermission;
use Inertia\Testing\AssertableInertia as Assert;

test('staff members without the permission are forbidden', function () {
    $this->actingAs(staffMember())
        ->get(route('admin.activity-log.index'))
        ->assertForbidden();
});

test('activities can be filtered by event', function () {
    $user = User::factory()->create();
    $user->update(['name' => 'Changed Name']);

    $this->actingAs(staffMember(ActivityLogPermission::ViewAny))
        ->get(route('admin.activity-log.index', ['filter' => ['event' => 'updated']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-log/Index')
            ->has('activities.data', 1)
            ->where('activities.data.0.event', 'updated')
            ->where('activities.data.0.subject_type', 'User')
            ->where('activities.data.0.changes.attributes.name', 'Changed Name'),
        );
});
