<?php

use App\Models\User;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use App\Modules\Foundation\Models\AuthenticationLog;
use App\Modules\Foundation\Notifications\NewUserRegistered;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * @return array<string, string>
 */
function credentials(User $user, array $extra = []): array
{
    return ['email' => $user->email, 'password' => 'password', 'device_name' => 'Test phone', ...$extra];
}

describe('signing in', function () {
    test('valid credentials give a token that expires and opens the api', function () {
        $user = User::factory()->withPermissions('User View List')->create();

        $response = $this->postJson(route('api.v1.auth.token.store'), credentials($user))
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.permissions', ['User View List']);

        expect(PersonalAccessToken::query()->sole())
            ->name->toBe('Test phone')
            ->expires_at->toDateString()->toBe(now()->addDays(30)->toDateString());

        $this->withToken($response->json('token'))
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        expect(AuthenticationLog::query()->sole()->event)->toBe(AuthenticationEvent::Login);
    });

    test('wrong credentials are refused in the requested language and recorded', function () {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.token.store'), credentials($user, ['password' => 'wrong']), ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('errors.email.0', __('auth.failed', locale: 'ar'))
            ->assertJsonStructure(['message', 'errors', 'request_id']);

        expect(PersonalAccessToken::query()->count())->toBe(0)
            ->and(AuthenticationLog::query()->sole()->event)->toBe(AuthenticationEvent::Failed);
    });

    test('signing in is rate limited against password guessing', function () {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.auth.token.store'), credentials($user, ['password' => 'wrong']))->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.token.store'), credentials($user))->assertTooManyRequests();
    });
});

describe('two-factor authentication', function () {
    test('an account with two-factor authentication needs a code', function () {
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('api.v1.auth.token.store'), credentials($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    test('a valid authenticator code gives a token', function () {
        $this->mock(TwoFactorAuthenticationProvider::class)
            ->shouldReceive('verify')->with('secret', '123456')->andReturn(true);
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('api.v1.auth.token.store'), credentials($user, ['code' => '123456']))->assertCreated();
    });

    test('a recovery code works once', function () {
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('api.v1.auth.token.store'), credentials($user, ['recovery_code' => 'recovery-code-1']))->assertCreated();
        $this->postJson(route('api.v1.auth.token.store'), credentials($user, ['recovery_code' => 'recovery-code-1']))->assertUnprocessable();
    });
});

test('signing out revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test phone')->plainTextToken;

    $this->withToken($token)->deleteJson(route('api.v1.auth.token.destroy'))->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('an expired token is refused', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Old phone', ['*'], now()->subMinute())->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
});

test('guests get a json 401 with the request id', function () {
    $this->getJson(route('api.v1.me'))
        ->assertUnauthorized()
        ->assertJsonStructure(['message', 'request_id']);
});

describe('notifications', function () {
    test('the user lists and reads their notifications', function () {
        $user = User::factory()->create();
        $user->notify(new NewUserRegistered(User::factory()->create(['name' => 'Sara'])));
        Sanctum::actingAs($user);

        $id = $this->getJson(route('api.v1.notifications.index', ['unread' => true]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'New user registered')
            ->json('data.0.id');

        $this->postJson(route('api.v1.notifications.read', ['notification' => $id]))->assertNoContent();

        expect($user->unreadNotifications()->count())->toBe(0);
    });

    test('nobody can read the notifications of someone else', function () {
        $owner = User::factory()->create();
        $owner->notify(new NewUserRegistered(User::factory()->create()));
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.notifications.read', ['notification' => $owner->notifications()->sole()->id]))->assertNotFound();
    });
});
