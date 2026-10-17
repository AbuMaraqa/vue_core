<?php

namespace App\Modules\Foundation\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The browser sessions a user is signed in with, read from the database
 * session store.
 *
 * Session ids are never sent to the browser: anyone holding one could take
 * over that session. Each session is identified by a keyed hash instead.
 */
class UserSessions
{
    /**
     * Sessions can only be listed when they are stored in the database.
     */
    public function isAvailable(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return list<array{key: string, ip_address: string|null, device: array{browser: string|null, platform: string|null, is_mobile: bool}, last_active_at: string, is_current: bool}>
     */
    public function for(User $user, string $currentSessionId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $sessions = $this->query($user)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'key' => $this->key($session->id),
                'ip_address' => $session->ip_address,
                'device' => UserAgent::parse($session->user_agent)->toArray(),
                'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => $session->id === $currentSessionId,
            ]);

        return array_values($sessions->all());
    }

    /**
     * Sign out the session with the given key. The current session is kept;
     * the user signs out of it with the normal sign-out button.
     */
    public function revoke(User $user, string $key, string $currentSessionId): bool
    {
        $sessionId = $this->query($user)
            ->where('id', '!=', $currentSessionId)
            ->pluck('id')
            ->first(fn (string $id): bool => hash_equals($this->key($id), $key));

        return $sessionId !== null && $this->query($user)->where('id', $sessionId)->delete() > 0;
    }

    /**
     * Sign out every session except the current one.
     */
    public function revokeOthers(User $user, string $currentSessionId): int
    {
        return $this->query($user)->where('id', '!=', $currentSessionId)->delete();
    }

    protected function query(User $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config()->string('session.table', 'sessions'))
            ->where('user_id', $user->id);
    }

    protected function key(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, config()->string('app.key'));
    }
}
