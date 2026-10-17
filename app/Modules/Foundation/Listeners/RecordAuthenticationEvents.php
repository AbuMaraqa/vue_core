<?php

namespace App\Modules\Foundation\Listeners;

use App\Models\User;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use App\Modules\Foundation\Models\AuthenticationLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;

/**
 * Write the login history from Laravel's authentication events, whichever way
 * the user signs in: password, two-factor challenge or passkey.
 *
 * The request is read when the event fires, so the listener is safe to reuse
 * across requests under Octane.
 */
class RecordAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        $this->record(AuthenticationEvent::Login, $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record(AuthenticationEvent::Logout, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->record(AuthenticationEvent::Failed, $event->user, is_string($email) ? $email : null);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record(AuthenticationEvent::Lockout, null, $event->request->string('email')->value() ?: null);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }

    protected function record(AuthenticationEvent $event, ?Authenticatable $user, ?string $email = null): void
    {
        $userId = $user?->getAuthIdentifier();

        AuthenticationLog::create([
            'user_id' => is_int($userId) ? $userId : null,
            'email' => $email ?? ($user instanceof User ? $user->email : null),
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
