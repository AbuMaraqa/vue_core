<?php

use App\Models\User;
use App\Modules\Foundation\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

describe('new user registered', function () {
    test('the staff who manage users and the super admins are notified', function () {
        Notification::fake();
        $userManager = User::factory()->withPermissions('User View List')->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $editor = User::factory()->withPermissions('Media View List')->create();
        $newUser = User::factory()->create();

        event(new Registered($newUser));

        Notification::assertSentTo([$userManager, $superAdmin], NewUserRegistered::class);
        Notification::assertNotSentTo([$editor, $newUser], NewUserRegistered::class);
    });

    test('it is shown translated with a link in the reader\'s language', function () {
        $reader = User::factory()->superAdmin()->create();
        $newUser = User::factory()->create(['name' => 'Sara', 'email' => 'sara@example.com']);
        $reader->notify(new NewUserRegistered($newUser));

        $this->actingAs($reader)
            ->get('/ar/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/notifications/Index')
                ->where('unreadNotifications', 1)
                ->where('notifications.data.0.title', __('New user registered', locale: 'ar'))
                ->where('notifications.data.0.body', __(':name (:email) created an account.', ['name' => 'Sara', 'email' => 'sara@example.com'], 'ar'))
                ->where('notifications.data.0.url', "/ar/admin/users/{$newUser->id}/edit")
                ->where('notifications.data.0.read_at', null),
            );
    });
});

test('the bell receives the unread count and the newest notifications', function () {
    $user = User::factory()->create();
    $user->notify(new NewUserRegistered(User::factory()->create()));
    $user->notify(new NewUserRegistered(User::factory()->create()));
    $user->notifications()->first()->markAsRead();

    $this->actingAs($user)
        ->getJson(route('notifications.latest'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(2, 'notifications');
});

test('the list can be filtered to unread notifications', function () {
    $user = User::factory()->create();
    $user->notify(new NewUserRegistered(User::factory()->create()));
    $user->notify(new NewUserRegistered(User::factory()->create()));
    $user->notifications()->first()->markAsRead();

    $this->actingAs($user)
        ->get(route('notifications.index', ['filter' => ['status' => 'unread']]))
        ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1));
});

test('opening a notification marks it as read and goes to its page', function () {
    $user = User::factory()->create();
    $newUser = User::factory()->create();
    $user->notify(new NewUserRegistered($newUser));
    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->post(route('notifications.open', ['notification' => $notification->id]))
        ->assertRedirect(route('admin.users.edit', $newUser, absolute: false));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('every notification can be marked as read at once', function () {
    $user = User::factory()->create();
    $user->notify(new NewUserRegistered(User::factory()->create()));
    $user->notify(new NewUserRegistered(User::factory()->create()));

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('nobody can open or delete the notifications of someone else', function () {
    $owner = User::factory()->create();
    $owner->notify(new NewUserRegistered(User::factory()->create()));
    $notification = $owner->notifications()->sole();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('notifications.open', ['notification' => $notification->id]))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->delete(route('notifications.destroy', ['notification' => $notification->id]))
        ->assertNotFound();

    expect($notification->fresh())->not->toBeNull()
        ->and($notification->fresh()->read_at)->toBeNull();
});
