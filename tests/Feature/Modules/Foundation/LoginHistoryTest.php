<?php

use App\Models\User;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use App\Modules\Foundation\Models\AuthenticationLog;
use Inertia\Testing\AssertableInertia as Assert;

describe('recording', function () {
    test('a successful sign-in is recorded with the device and ip address', function () {
        $user = User::factory()->create();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile Safari/604.1')
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $log = AuthenticationLog::query()->sole();

        expect($log->event)->toBe(AuthenticationEvent::Login)
            ->and($log->user_id)->toBe($user->id)
            ->and($log->email)->toBe($user->email)
            ->and($log->ip_address)->toBe('127.0.0.1')
            ->and($log->user_agent)->toContain('iPhone');
    });

    test('a failed attempt keeps the attempted email even without an account', function () {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $log = AuthenticationLog::query()->sole();

        expect($log->event)->toBe(AuthenticationEvent::Failed)
            ->and($log->user_id)->toBeNull()
            ->and($log->email)->toBe('nobody@example.com');
    });

    test('signing out is recorded', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        expect(AuthenticationLog::query()->sole())
            ->event->toBe(AuthenticationEvent::Logout)
            ->user_id->toBe($user->id);
    });

    test('entries older than the retention period are pruned', function () {
        config(['foundation.login_history.retention_days' => 30]);
        $old = AuthenticationLog::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = AuthenticationLog::factory()->create(['created_at' => now()->subDays(29)]);

        $this->artisan('model:prune', ['--model' => [AuthenticationLog::class]]);

        expect(AuthenticationLog::query()->pluck('id')->all())->toBe([$recent->id])
            ->and($old->fresh())->toBeNull();
    });
});

describe('admin page', function () {
    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.login-history.index'))
            ->assertForbidden();
    });

    test('the history can be filtered by event and searched by email', function () {
        AuthenticationLog::factory()->create(['email' => 'sara@example.com']);
        AuthenticationLog::factory()->failed('sara@example.com')->create();
        AuthenticationLog::factory()->failed('omar@example.com')->create();

        $this->actingAs(staffMember('Login History View List'))
            ->get(route('admin.login-history.index', ['filter' => ['event' => 'failed', 'search' => 'sara']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/login-history/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.email', 'sara@example.com')
                ->where('logs.data.0.event', 'failed')
                ->where('logs.data.0.device.browser', 'Chrome')
                ->where('logs.data.0.device.platform', 'Windows')
                ->has('events', count(AuthenticationEvent::cases())),
            );
    });
});
