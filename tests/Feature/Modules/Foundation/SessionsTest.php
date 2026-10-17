<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['session.driver' => 'database']);

    $this->user = User::factory()->create();
});

/**
 * Store a browser session of the given user in the database session table.
 */
function storeSession(User $user, string $id, string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Firefox/131.0'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.5',
        'user_agent' => $userAgent,
        'payload' => '',
        'last_activity' => now()->subHour()->getTimestamp(),
    ]);
}

/**
 * Open the sessions page as the user, after a recent password confirmation.
 */
function visitSessions(): TestResponse
{
    return test()->actingAs(test()->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('sessions.index'));
}

test('the page asks for the password first', function () {
    $this->actingAs($this->user)
        ->get(route('sessions.index'))
        ->assertRedirect(route('password.confirm'));
});

test('the user sees their own sessions without their real ids', function () {
    storeSession($this->user, 'other-session-id-of-the-user');
    storeSession(User::factory()->create(), 'session-of-someone-else');

    $response = visitSessions()->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/Sessions')
        ->where('available', true)
        ->has('sessions', 1)
        ->where('sessions.0.device.browser', 'Firefox')
        ->where('sessions.0.device.platform', 'macOS')
        ->where('sessions.0.is_current', false),
    );

    $response->assertDontSee('other-session-id-of-the-user')->assertDontSee('session-of-someone-else');
});

test('one session can be signed out', function () {
    storeSession($this->user, 'stolen-laptop-session');

    $key = visitSessions()->inertiaProps('sessions.0.key');

    $this->delete(route('sessions.destroy', ['sessionKey' => $key]))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect(DB::table('sessions')->where('id', 'stolen-laptop-session')->exists())->toBeFalse();
});

test('a session of another user can never be signed out', function () {
    $otherUser = User::factory()->create();
    storeSession($otherUser, 'victim-session');

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('sessions.destroy', ['sessionKey' => hash_hmac('sha256', 'victim-session', config('app.key'))]))
        ->assertNotFound();

    expect(DB::table('sessions')->where('id', 'victim-session')->exists())->toBeTrue();
});

test('every other session can be signed out at once', function () {
    storeSession($this->user, 'old-phone-session');
    storeSession($this->user, 'old-tablet-session');
    storeSession($otherUser = User::factory()->create(), 'unrelated-session');

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect();

    expect(DB::table('sessions')->whereIn('id', ['old-phone-session', 'old-tablet-session'])->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-session')->exists())->toBeTrue();
});

test('the page explains when sessions are not stored in the database', function () {
    config(['session.driver' => 'array']);

    visitSessions()->assertInertia(fn (Assert $page) => $page
        ->where('available', false)
        ->where('sessions', []),
    );
});
